<?php

return [
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) env('EMC_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1')),
    ))),
    'upload_max_bytes' => (int) env('EMC_UPLOAD_MAX_BYTES', 5 * 1024 * 1024),
    'order_photo_retention_days' => (int) env('EMC_ORDER_PHOTO_RETENTION_DAYS', 0),
    'admin_remember_days' => (int) env('EMC_ADMIN_REMEMBER_DAYS', 30),
];
