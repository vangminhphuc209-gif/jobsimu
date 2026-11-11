<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Công cụ này chỉ chạy qua dòng lệnh (CLI).');
}

$args = array_slice($argv, 1);
$dryRun = false;
$runMigrate = true;
$jsonOutput = false;
$reportPath = '';
$sourcePath = dirname(__DIR__) . '/jobsimu_hnv_data';

foreach ($args as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo "JobSimu HNV Training Majors Importer\n";
        echo "Cách dùng:\n";
        echo "  php scripts/import_hnv_majors.php [tùy chọn]\n\n";
        echo "Tùy chọn:\n";
        echo "  --dry-run        Xem trước thay đổi mà không ghi vào cơ sở dữ liệu\n";
        echo "  --source=PATH    Đường dẫn tới thư mục dữ liệu hoặc tệp jobsimu_hnv.json\n";
        echo "                   (mặc định: ./jobsimu_hnv_data)\n";
        echo "  --no-migrate     Không tự chạy migration trước khi import\n";
        echo "  --json           Xuất báo cáo dạng JSON\n";
        echo "  --report=FILE    Lưu báo cáo JSON ra tệp\n";
        echo "  --help, -h       Hiển thị hướng dẫn này\n";
        exit(0);
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--no-migrate') {
        $runMigrate = false;
    } elseif ($arg === '--migrate') {
        $runMigrate = true;
    } elseif ($arg === '--json') {
        $jsonOutput = true;
    } elseif (strpos($arg, '--source=') === 0) {
        $sourcePath = substr($arg, strlen('--source='));
    } elseif (strpos($arg, '--report=') === 0) {
        $reportPath = substr($arg, strlen('--report='));
    } else {
        fwrite(STDERR, "Tham số không hợp lệ: $arg (dùng --help để xem hướng dẫn)\n");
        exit(2);
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/training-majors.php';
require_once ROOT_PATH . '/app/training-majors-importer.php';

try {
    $db = db();
    if ($runMigrate) {
        training_majors_migrate($db);
    }

    $report = hnv_import_dataset($db, $sourcePath, [
        'dry_run' => $dryRun,
    ]);

    if ($reportPath !== '') {
        file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    }

    if ($jsonOutput) {
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }

    $modeLabel = $dryRun ? 'DRY-RUN (Xem trước — KHÔNG ghi vào CSDL)' : 'IMPORT THÀNH CÔNG (Đã lưu trong transaction)';
    echo "============================================================\n";
    echo "JOBSIMU · NHẬP DỮ LIỆU NGÀNH ĐÀO TẠO (HNV)\n";
    echo "Chế độ   : {$modeLabel}\n";
    echo "Nguồn    : {$report['source_url']} (schema {$report['schema_version']})\n";
    echo "Thu thập : " . ($report['retrieved_at'] ?? 'N/A') . "\n";
    echo "------------------------------------------------------------\n";
    echo sprintf(
        "Dữ liệu đầu vào: %d lĩnh vực | %d nhóm ngành | %d ngành | %d URL nguồn trường | %d liên kết | %d hàng chờ rà soát\n",
        $report['dataset_counts']['fields'],
        $report['dataset_counts']['subgroups'],
        $report['dataset_counts']['majors'],
        $report['dataset_counts']['institution_source_records'],
        $report['dataset_counts']['major_institution_links'],
        $report['dataset_counts']['review_queue']
    );
    echo "------------------------------------------------------------\n";
    echo "Chi tiết theo bảng:\n";
    foreach ($report['tables'] as $table => $counts) {
        echo sprintf(
            "  %-34s +%-5d thêm | ~%-5d cập nhật | =%-5d bỏ qua | *%-4d bảo toàn\n",
            $table,
            $counts['inserted'] ?? 0,
            $counts['updated'] ?? 0,
            $counts['skipped'] ?? 0,
            $counts['preserved_editorial'] ?? 0
        );
    }
    echo "------------------------------------------------------------\n";
    $t = $report['totals'];
    echo sprintf(
        "TỔNG KẾT: Thêm mới=%d | Cập nhật=%d | Bỏ qua=%d | Bảo toàn biên tập=%d | Cần xử lý (review queue)=%d | Ánh xạ chờ duyệt=%d\n",
        $t['inserted'],
        $t['updated'],
        $t['skipped'],
        $t['preserved_editorial'],
        $t['needs_review'],
        $t['pending_mappings']
    );
    if (!empty($report['missing_data_breakdown'])) {
        echo "Tình trạng thiếu dữ liệu / cần xác minh:\n";
        foreach ($report['missing_data_breakdown'] as $code => $cnt) {
            echo "  - {$code}: {$cnt} ngành\n";
        }
    }
    if (!empty($report['warnings'])) {
        echo "Cảnh báo:\n";
        foreach ($report['warnings'] as $w) {
            echo "  ! {$w}\n";
        }
    }
    if ($reportPath !== '') {
        echo "Đã xuất báo cáo JSON tại: {$reportPath}\n";
    }
    echo "============================================================\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'LỖI IMPORT: ' . $e->getMessage() . "\n");
    exit(1);
}
