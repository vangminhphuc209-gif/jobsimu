<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));

// PHP 7.4 compatibility polyfills
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || $needle === substr($haystack, -strlen($needle));
    }
}

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
                $curServer = trim((string)($_SERVER[$key] ?? ''));
                $curEnv = trim((string)($_ENV[$key] ?? ''));
                if ($curServer === '' && $curEnv === '') {
                    if (function_exists('putenv')) {
                        @putenv("{$key}={$value}");
                    }
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

$configFile = ROOT_PATH . '/config.php';
if (getenv('JOBSIMU_FORCE_ENV_DB') === '1' && is_file(ROOT_PATH . '/config.example.php')) {
    $configFile = ROOT_PATH . '/config.example.php';
} elseif (!is_file($configFile)) {
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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name'] ?? 'jobsimu_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$db = $config['db'];
$dsn = ($db['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite:' . $db['path'] : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $bufferedQueryAttr = defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY') ? constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY') : (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') ? constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') : null);
    if (($db['driver'] ?? 'mysql') !== 'sqlite' && $bufferedQueryAttr !== null) {
        $pdoOptions[$bufferedQueryAttr] = true;
    }
    $pdo = new PDO($dsn, $db['user'] ?? null, $db['pass'] ?? null, $pdoOptions);
} catch (PDOException $e) {
    http_response_code(500);
    exit(!empty($config['debug']) ? 'Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()) : 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra config.php.');
}

require_once ROOT_PATH . '/app/functions.php';
require_once ROOT_PATH . '/app/journey-flags.php';
require_once ROOT_PATH . '/app/journey-migrations.php';

if (!function_exists('bootstrap_run_migrations_if_needed')) {
    function bootstrap_run_migrations_if_needed(): void {
        if (getenv('JOBSIMU_RUN_MIGRATIONS') === '1' || getenv('JOBSIMU_FORCE_MIGRATIONS') === '1') {
            bootstrap_execute_all_migrations();
            return;
        }
        $codeVersion = '4.0.0_1.1.0_1.0.0';
        $lockFile = sys_get_temp_dir() . '/jobsimu_schema_' . md5(ROOT_PATH) . '.lock';
        if (is_file($lockFile) && trim((string)@file_get_contents($lockFile)) === $codeVersion) {
            return;
        }
        bootstrap_execute_all_migrations();
        @file_put_contents($lockFile, $codeVersion);
    }
}

if (!function_exists('bootstrap_execute_all_migrations')) {
    function bootstrap_execute_all_migrations(): void {
    run_migrations();
    require_once ROOT_PATH . '/app/career-admissions.php';
    run_admissions_migrations();
    require_once ROOT_PATH . '/app/admissions-p2.php';
    admissions_p2_migrate();
    require_once ROOT_PATH . '/app/admissions-p22.php';
    admissions_p22_migrate();
    admissions_p22_tuition_seed();
    require_once ROOT_PATH . '/app/admissions-p22-notices.php';
    admissions_p22_notice_migrate();
    admissions_p22_notice_seed();
    require_once ROOT_PATH . '/app/training-majors.php';
    training_majors_migrate();
    require_once ROOT_PATH . '/app/schools.php';
    schools_migrate();
    require_once ROOT_PATH . '/app/journey-flags.php';
    require_once ROOT_PATH . '/app/journey-migrations.php';
    journey_run_migrations();
    }
}

bootstrap_run_migrations_if_needed();


