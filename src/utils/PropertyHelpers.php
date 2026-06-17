<?php

if (!function_exists('sukhdham_property_type_options')) {
    function sukhdham_property_type_options()
    {
        return ['sell', 'rent'];
    }
}

if (!function_exists('sukhdham_property_category_options')) {
    function sukhdham_property_category_options()
    {
        return ['Apartment', 'House', 'Villa', 'Commercial Shop', 'Commercial', 'Office', 'Warehouse', 'Plot', 'Other'];
    }
}

if (!function_exists('sukhdham_property_status_options')) {
    function sukhdham_property_status_options()
    {
        return ['available', 'rented', 'sold'];
    }
}

if (!function_exists('sukhdham_furnishing_options')) {
    function sukhdham_furnishing_options()
    {
        return ['Furnished', 'Semi Furnished', 'Unfurnished'];
    }
}

if (!function_exists('sukhdham_water_supply_options')) {
    function sukhdham_water_supply_options()
    {
        return ['24 Hours', 'Limited'];
    }
}

if (!function_exists('sukhdham_facing_options')) {
    function sukhdham_facing_options()
    {
        return ['East', 'West', 'North', 'South'];
    }
}

if (!function_exists('sukhdham_food_preference_options')) {
    function sukhdham_food_preference_options()
    {
        return ['Veg', 'Non Veg', 'Both'];
    }
}

if (!function_exists('sukhdham_tenant_preferred_options')) {
    function sukhdham_tenant_preferred_options()
    {
        return ['Family', 'Bachelor', 'Students', 'Any'];
    }
}

if (!function_exists('sukhdham_amenity_options')) {
    function sukhdham_amenity_options()
    {
        return [
            'Lift',
            'CCTV',
            'Security Guard',
            'WiFi',
            'AC',
            'Power Backup',
            'Garden',
            'Gym',
            'Swimming Pool',
            'Kids Play Area',
            'Gas Pipeline',
            'Modular Kitchen',
            'Balcony',
            'Terrace',
            'Visitor Parking',
            'Gated Society',
            'Nearby Market',
            'Nearby School',
            'Nearby Hospital',
            'Pet Friendly',
        ];
    }
}

if (!function_exists('sukhdham_normalize_selection')) {
    function sukhdham_normalize_selection($value, array $allowed, $default)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}

if (!function_exists('sukhdham_normalize_yes_no')) {
    function sukhdham_normalize_yes_no($value, $default = 'No')
    {
        return strtolower((string) $value) === 'yes' ? 'Yes' : $default;
    }
}

if (!function_exists('sukhdham_bind_stmt_values')) {
    function sukhdham_bind_stmt_values(mysqli_stmt $stmt, $types, array $values)
    {
        $bindValues = [$types];

        foreach ($values as $index => $value) {
            $bindValues[] = &$values[$index];
        }

        return call_user_func_array([$stmt, 'bind_param'], $bindValues);
    }
}

if (!function_exists('sukhdham_normalize_property_form')) {
    function sukhdham_normalize_property_form(array $post)
    {
        $propertyType = sukhdham_normalize_selection(
            $post['property_type'] ?? 'sell',
            sukhdham_property_type_options(),
            'sell'
        );

        $category = sukhdham_normalize_selection(
            $post['category'] ?? 'Apartment',
            sukhdham_property_category_options(),
            'Apartment'
        );

        $propertyStatus = sukhdham_normalize_selection(
            $post['property_status'] ?? 'available',
            sukhdham_property_status_options(),
            'available'
        );

        $furnishingType = sukhdham_normalize_selection(
            $post['furnishing_type'] ?? 'Unfurnished',
            sukhdham_furnishing_options(),
            'Unfurnished'
        );

        $waterSupply = sukhdham_normalize_selection(
            $post['water_supply'] ?? '24 Hours',
            sukhdham_water_supply_options(),
            '24 Hours'
        );

        $facingDirection = sukhdham_normalize_selection(
            $post['facing_direction'] ?? 'East',
            sukhdham_facing_options(),
            'East'
        );

        $foodPreference = sukhdham_normalize_selection(
            $post['food_preference'] ?? 'Both',
            sukhdham_food_preference_options(),
            'Both'
        );

        $tenantPreferred = sukhdham_normalize_selection(
            $post['tenant_preferred'] ?? 'Any',
            sukhdham_tenant_preferred_options(),
            'Any'
        );

        $amenities = $post['amenities'] ?? [];
        if (!is_array($amenities)) {
            $amenities = [];
        }
        $amenities = array_values(array_intersect($amenities, sukhdham_amenity_options()));

        $availableFrom = trim((string) ($post['available_from'] ?? ''));
        if ($availableFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $availableFrom)) {
            $availableFrom = '';
        }

        return [
            'description' => trim((string) ($post['description'] ?? '')),
            'address' => trim((string) ($post['address'] ?? '')),
            'property_type' => $propertyType,
            'category' => $category,
            'property_status' => $propertyStatus,
            'booking_enabled' => !empty($post['booking_enabled']) ? 1 : 0,
            'price' => trim((string) ($post['price'] ?? '')) !== '' ? (float) $post['price'] : null,
            'security_deposit' => trim((string) ($post['security_deposit'] ?? '')) !== '' ? (float) $post['security_deposit'] : null,
            'maintenance_charges' => trim((string) ($post['maintenance_charges'] ?? '')) !== '' ? (float) $post['maintenance_charges'] : null,
            'available_from' => $availableFrom !== '' ? $availableFrom : null,
            'furnishing_type' => $furnishingType,
            'bedrooms' => (int) ($post['bedrooms'] ?? 0),
            'bathrooms' => (int) ($post['bathrooms'] ?? 0),
            'balconies' => (int) ($post['balconies'] ?? 0),
            'floor_number' => (int) ($post['floor_number'] ?? 0),
            'total_floors' => (int) ($post['total_floors'] ?? 0),
            'area_sqft' => (int) ($post['area_sqft'] ?? 0),
            'carpet_area' => trim((string) ($post['carpet_area'] ?? '')) !== '' ? (float) $post['carpet_area'] : null,
            'parking' => sukhdham_normalize_yes_no($post['parking'] ?? 'No'),
            'water_supply' => $waterSupply,
            'electricity_backup' => sukhdham_normalize_yes_no($post['electricity_backup'] ?? 'No'),
            'facing_direction' => $facingDirection,
            'food_preference' => $foodPreference,
            'amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE),
            'tenant_preferred' => $tenantPreferred,
            'lease_duration' => trim((string) ($post['lease_duration'] ?? '')),
            'available_immediately' => sukhdham_normalize_yes_no($post['available_immediately'] ?? 'No'),
            'bills_included' => sukhdham_normalize_yes_no($post['bills_included'] ?? 'No'),
            'pets_allowed' => sukhdham_normalize_yes_no($post['pets_allowed'] ?? 'No'),
            'washroom_available' => sukhdham_normalize_yes_no($post['washroom_available'] ?? 'No'),
            'pantry_available' => sukhdham_normalize_yes_no($post['pantry_available'] ?? 'No'),
            'cabin_count' => (int) ($post['cabin_count'] ?? 0),
            'parking_spaces' => (int) ($post['parking_spaces'] ?? 0),
        ];
    }
}

if (!function_exists('sukhdham_decode_property_amenities')) {
    function sukhdham_decode_property_amenities($value)
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_intersect($decoded, sukhdham_amenity_options())) : [];
    }
}

if (!function_exists('sukhdham_property_amenity_icon')) {
    function sukhdham_property_amenity_icon($amenity)
    {
        $icons = [
            'Lift' => '⬆',
            'CCTV' => '📷',
            'Security Guard' => '🛡',
            'WiFi' => '📶',
            'AC' => '❄',
            'Power Backup' => '🔋',
            'Garden' => '🌿',
            'Gym' => '🏋',
            'Swimming Pool' => '🏊',
            'Kids Play Area' => '🧸',
            'Gas Pipeline' => '🔥',
            'Modular Kitchen' => '🍳',
            'Balcony' => '🏠',
            'Terrace' => '🏗',
            'Visitor Parking' => '🅿',
            'Gated Society' => '🚪',
            'Nearby Market' => '🛒',
            'Nearby School' => '🏫',
            'Nearby Hospital' => '🏥',
            'Pet Friendly' => '🐾',
        ];

        return $icons[$amenity] ?? '✓';
    }
}

if (!function_exists('sukhdham_save_base64_property_image')) {
    function sukhdham_save_base64_property_image($base64_data, $property_id, $watermarkText = 'Sukhdham')
    {
        $timestamp = time();
        $random = rand(1000, 9999);
        $filename = 'property_' . $property_id . '_' . $timestamp . '_' . $random . '.jpg';
        $filepath = UPLOAD_DIR . $filename;

        $data = explode(',', (string) $base64_data, 2);
        if (count($data) < 2) {
            return ['success' => false, 'error' => 'Invalid image data'];
        }

        $image_content = base64_decode($data[1]);
        if ($image_content === false) {
            return ['success' => false, 'error' => 'Base64 decode failed'];
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            if (file_put_contents($filepath, $image_content) === false) {
                return ['success' => false, 'error' => 'Failed to save image'];
            }

            return ['success' => true, 'url' => UPLOAD_URL . $filename];
        }

        $source = @imagecreatefromstring($image_content);
        if ($source === false) {
            if (file_put_contents($filepath, $image_content) === false) {
                return ['success' => false, 'error' => 'Failed to save image'];
            }

            return ['success' => true, 'url' => UPLOAD_URL . $filename];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        $padding = max(12, (int) round($width * 0.02));
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($watermarkText);
        $textHeight = imagefontheight($font);
        $x = max($padding, $width - $textWidth - ($padding * 2));
        $y = max($padding, $height - $textHeight - ($padding * 1.5));

        $overlayColor = imagecolorallocatealpha($canvas, 17, 24, 39, 70);
        $textColor = imagecolorallocate($canvas, 255, 255, 255);

        imagefilledrectangle(
            $canvas,
            $x - 8,
            $y - 6,
            min($width - 1, $x + $textWidth + 8),
            min($height - 1, $y + $textHeight + 6),
            $overlayColor
        );
        imagestring($canvas, $font, $x, $y, $watermarkText, $textColor);

        imagejpeg($canvas, $filepath, 88);

        imagedestroy($source);
        imagedestroy($canvas);

        return ['success' => true, 'url' => UPLOAD_URL . $filename];
    }
}

?>