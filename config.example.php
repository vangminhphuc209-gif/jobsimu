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
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'path' => getenv('DB_PATH') ?: (__DIR__ . '/database/local.sqlite'),
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
    // Cấu hình AI Chatbot Trợ lý La Bàn (tùy chọn trong config.php nếu không dùng .env hoặc database)
    'ai' => [
        'groq_api_key' => getenv('GROQ_API_KEY') ?: '',
        'groq_model' => getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b',
        'cloudflare_api_token' => getenv('CLOUDFLARE_API_TOKEN') ?: '',
        'cloudflare_account_id' => getenv('CLOUDFLARE_ACCOUNT_ID') ?: '',
        'cloudflare_model' => getenv('CLOUDFLARE_MODEL') ?: '@cf/meta/llama-3.1-8b-instruct-fp8-fast',
    ],
];
