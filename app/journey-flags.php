<?php
declare(strict_types=1);

/**
 * Feature flags for additive 4-step career guidance modules.
 * Allows disabling any or all new modules via environment variables
 * (JOBSIMU_FEATURE_<KEY>=0) or database settings (`feature_<key>=0`)
 * while preserving 100% of legacy JobSimu routes, assessments, and data.
 */

function journey_feature_registry(): array
{
    return [
        'journey_4step' => [
            'env' => 'JOBSIMU_FEATURE_JOURNEY_4STEP',
            'setting' => 'feature_journey_4step',
            'default' => true,
            'label' => 'Hành trình 4 bước trên điều hướng & trang chủ',
        ],
        'discover_quick' => [
            'env' => 'JOBSIMU_FEATURE_DISCOVER_QUICK',
            'setting' => 'feature_discover_quick',
            'default' => true,
            'label' => 'Bước 1: Khám phá nhanh (5 câu bổ sung)',
        ],
        'lookup_hub' => [
            'env' => 'JOBSIMU_FEATURE_LOOKUP_HUB',
            'setting' => 'feature_lookup_hub',
            'default' => true,
            'label' => 'Bước 2: Trung tâm Tra cứu (Ngành / Trường / Tổ hợp)',
        ],
        'compare_priority' => [
            'env' => 'JOBSIMU_FEATURE_COMPARE_PRIORITY',
            'setting' => 'feature_compare_priority',
            'default' => true,
            'label' => 'Bước 3: Hiểu mình cần gì (So sánh ưu tiên 20 câu)',
        ],
        'action_plan' => [
            'env' => 'JOBSIMU_FEATURE_ACTION_PLAN',
            'setting' => 'feature_action_plan',
            'default' => true,
            'label' => 'Bước 4: Lập kế hoạch theo tháng/năm',
        ],
    ];
}

function journey_parse_bool_flag($raw, bool $default): bool
{
    if ($raw === null || $raw === '') {
        return $default;
    }
    $val = strtolower(trim((string)$raw));
    if (in_array($val, ['0', 'false', 'off', 'no', 'disabled'], true)) {
        return false;
    }
    if (in_array($val, ['1', 'true', 'on', 'yes', 'enabled'], true)) {
        return true;
    }
    return $default;
}

function journey_feature_enabled(string $feature): bool
{
    $reg = journey_feature_registry();
    if (!isset($reg[$feature])) {
        return false;
    }
    $cfg = $reg[$feature];

    // 1. Environment variable override (highest priority, useful for safe rollback/testing)
    $envRaw = getenv($cfg['env']);
    if ($envRaw !== false && trim((string)$envRaw) !== '') {
        return journey_parse_bool_flag($envRaw, (bool)$cfg['default']);
    }

    // 2. Database `settings` table toggle (runtime admin toggle without code deploy)
    if (function_exists('setting')) {
        try {
            $dbVal = setting($cfg['setting'], '');
            if ($dbVal !== '') {
                return journey_parse_bool_flag($dbVal, (bool)$cfg['default']);
            }
        } catch (Throwable $ignored) {
            // Fall through to default safely if DB is unavailable
        }
    }

    return (bool)$cfg['default'];
}

function journey_guard_page(string $feature, string $fallbackUrl, string $label): bool
{
    if (journey_feature_enabled($feature)) {
        return true;
    }
    if (function_exists('flash')) {
        flash('info', $label . ' đang tạm tắt. Bạn vẫn có thể sử dụng đầy đủ các công cụ hiện có của JobSimu.');
    }
    redirect($fallbackUrl);
    return false;
}

function journey_guard_api(string $feature): bool
{
    if (journey_feature_enabled($feature)) {
        return true;
    }
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'code' => 'FEATURE_DISABLED',
        'error' => 'Tính năng bổ sung này hiện đang tạm tắt.',
    ], JSON_UNESCAPED_UNICODE);
    return false;
}
