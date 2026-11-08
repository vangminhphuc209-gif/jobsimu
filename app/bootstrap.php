<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
// Tự động nạp biến môi trường từ file .env nếu có (không phụ thuộc thư viện ngoài)
$envFile = ROOT_PATH . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

$configFile = ROOT_PATH . '/config.php';
if (!is_file($configFile)) {
    if (is_file(ROOT_PATH . '/config.example.php') && getenv('DB_NAME')) {
        $configFile = ROOT_PATH . '/config.example.php';
    } elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
        header('Location: setup.php');
        exit;
    } else {
        return;
    }
}

$config = require $configFile;
date_default_timezone_set('Asia/Ho_Chi_Minh');
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
error_reporting(E_ALL);

session_name($config['session_name'] ?? 'jobsimu_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$db = $config['db'];
$dsn = ($db['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite:' . $db['path'] : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
try {
    $pdo = new PDO($dsn, $db['user'] ?? null, $db['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit(!empty($config['debug']) ? 'Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()) : 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra config.php.');
}

require_once ROOT_PATH . '/app/functions.php';
run_migrations();
