<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
$root = __DIR__;
$locked = is_file($root . '/config.php');
$done = $locked;
$error = '';

if ($locked && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $error = 'Hệ thống đã được cài đặt và khóa an toàn. Để cài đặt lại, vui lòng xóa file config.php trước.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$locked) {
    $host = trim($_POST['host'] ?? 'localhost');
    $port = (int)($_POST['port'] ?? 3306);
    $name = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? 'Quản trị viên');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    try {
        if (!$name || !$user || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPass) < 8) {
            throw new RuntimeException('Vui lòng nhập đủ thông tin; mật khẩu quản trị tối thiểu 8 ký tự.');
        }
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec(file_get_contents($root . '/database/schema.sql'));
        $pdo->exec(file_get_contents($root . '/database/seed.sql'));
        $stmt = $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin') ON DUPLICATE KEY UPDATE name=VALUES(name),password=VALUES(password),role='admin'");
        $stmt->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);
        $config = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export([
            'app_name' => 'Hướng Nghiệp 360',
            'base_url' => rtrim(dirname((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['SCRIPT_NAME'] ?? '/setup.php')), '/\\'),
            'db' => [
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
                'charset' => 'utf8mb4'
            ],
            'session_name' => 'jobsimu_session',
            'debug' => false
        ], true) . ";\n";
        if (file_put_contents($root . '/config.php', $config) === false) {
            throw new RuntimeException('Không ghi được config.php. Hãy cấp quyền ghi tạm cho thư mục.');
        }
        $done = true;
        $locked = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cài đặt Hướng Nghiệp 360</title><style>body{font:16px system-ui,-apple-system,sans-serif;background:#f3f6ff;color:#17223b;margin:0}.box{max-width:700px;margin:5vh auto;background:white;padding:36px;border-radius:24px;box-shadow:0 20px 60px #253b8018}h1{color:#425eea;margin-top:0}label{display:block;margin:15px 0 6px;font-weight:650}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #ccd3e1;border-radius:10px;font:inherit}button,a.btn-link{display:inline-block;margin-top:22px;background:#526df0;color:white;padding:14px 22px;border:0;border-radius:11px;text-decoration:none;font-weight:700}.btn-sec{background:#e2e8f0;color:#334155}.error{background:#fee2e2;padding:12px;border-radius:10px;color:#991b1b;margin-bottom:16px}.alert-locked{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:16px;border-radius:12px;line-height:1.6}@media(max-width:600px){.grid{grid-template-columns:1fr}.box{margin:0;border-radius:0}}</style></head><body><div class="box"><?php if($locked): ?><h1>🔒 Trình cài đặt đã khóa</h1><div class="alert-locked"><p><b>Hệ thống Hướng Nghiệp 360 đã được cài đặt hoàn tất.</b></p><p>Tệp cấu hình <code>config.php</code> đã tồn tại. Để bảo mật hệ thống và ngăn chặn ghi đè CSDL, trình cài đặt đã tự động khóa lại.</p><p style="margin-bottom:0">Vì an toàn tối đa ở môi trường triển khai thực tế, bạn có thể xóa hoặc đổi tên tệp <b>setup.php</b>.</p></div><div style="display:flex;gap:12px"><a class="btn-link" href="index.php">Mở website</a> <a class="btn-link btn-sec" href="admin.php">Vào quản trị</a></div><?php else: ?><h1>Hướng Nghiệp 360</h1><p>Nhập thông tin MySQL đã tạo trong cPanel. Bộ cài sẽ tạo bảng, dữ liệu mẫu và tài khoản quản trị.</p><?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post"><div class="grid"><div><label>Máy chủ MySQL</label><input name="host" value="<?=htmlspecialchars($_POST['host']??'localhost')?>" required></div><div><label>Cổng</label><input name="port" type="number" value="3306" required></div></div><label>Tên database</label><input name="name" value="<?=htmlspecialchars($_POST['name']??'')?>" required><div class="grid"><div><label>User database</label><input name="user" required></div><div><label>Mật khẩu database</label><input name="pass" type="password"></div></div><hr style="border:0;border-top:1px solid #eee;margin-top:28px"><div class="grid"><div><label>Tên quản trị</label><input name="admin_name" value="Quản trị viên" required></div><div><label>Email quản trị</label><input name="admin_email" type="email" required></div></div><label>Mật khẩu quản trị (tối thiểu 8 ký tự)</label><input name="admin_pass" type="password" minlength="8" required><button>Cài đặt website</button></form><?php endif; ?></div></body></html>
