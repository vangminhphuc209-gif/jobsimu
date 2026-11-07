<?php
declare(strict_types=1);

/**
 * File cấu hình mẫu cho hệ thống Hướng Nghiệp 360
 *
 * Hướng dẫn sử dụng:
 * 1. Sao chép file này thành 'config.php': cp config.example.php config.php
 * 2. Điền thông tin kết nối CSDL và cấu hình ứng dụng thực tế vào config.php.
 * 3. TUYỆT ĐỐI KHÔNG commit file 'config.php' lên Git.
 */

return [
    'app_name' => getenv('APP_NAME') ?: 'Hướng Nghiệp 360',
    'base_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'jobsimu',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'session_name' => getenv('SESSION_NAME') ?: 'jobsimu_session',
    // Luôn đặt debug = false trên môi trường production
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
];
