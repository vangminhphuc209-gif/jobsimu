<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/may-knowledge.php';

function expect_knowledge(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("Knowledge test failed: {$msg}");
}

echo "Running Mây Knowledge Retrieval Tests...\n";

// 1. Tokenizer tests
$tokens = may_tokenize_query('Làm sao để biết mình có hợp với ngành CNTT hay không?');
expect_knowledge(in_array('cntt', $tokens, true), 'Token cntt extracted');
expect_knowledge(!in_array('là', $tokens, true) && !in_array('mình', $tokens, true), 'Stop words removed');

// 2. Career search
$careers = may_search_careers('lập trình viên', 2);
expect_knowledge(count($careers) > 0, 'Found programmer career');
expect_knowledge($careers[0]['type'] === 'career', 'Type is career');
expect_knowledge($careers[0]['status'] === 'published', 'Status is published');
expect_knowledge(str_contains($careers[0]['url'], 'index.php?page=career'), 'Valid career URL');

// 3. Major search
$majors = may_search_majors('Khoa học máy tính', 2);
expect_knowledge(count($majors) > 0, 'Found Computer Science major');
expect_knowledge($majors[0]['type'] === 'major', 'Type is major');
expect_knowledge(!empty($majors[0]['major_code']), 'Major has code');

// 4. Admission cutoffs search
$cutoffs = may_search_admissions('điểm chuẩn Bách khoa', 4);
expect_knowledge(count($cutoffs) > 0, 'Found admission cutoffs for Bach Khoa');
expect_knowledge($cutoffs[0]['type'] === 'admission', 'Type is admission');
expect_knowledge(isset($cutoffs[0]['year']) && !empty($cutoffs[0]['year']), 'Cutoff has year');

// 5. Tuition search
$tuition = may_search_admissions('học phí Bách khoa', 4);
expect_knowledge(count($tuition) > 0, 'Found tuition bands');
expect_knowledge($tuition[0]['type'] === 'tuition', 'Type is tuition');
expect_knowledge(str_contains($tuition[0]['summary'], 'VNĐ'), 'Tuition specifies currency');

// 6. Scenario search
$scenarios = may_search_scenarios('giáo viên bài học', 2);
expect_knowledge(count($scenarios) > 0, 'Found teacher scenario');
expect_knowledge($scenarios[0]['type'] === 'scenario', 'Type is scenario');
expect_knowledge($scenarios[0]['status'] === 'hypothetical', 'Scenario is clearly marked hypothetical');

// 7. Grounded retrieval with missing data notice
$missing = may_retrieve_evidence('điểm chuẩn trường đại học xyz hoàn toàn không tồn tại');
expect_knowledge(!empty($missing['missing_data_notice']), 'Missing data notice returned for unknown school');
expect_knowledge(str_contains($missing['missing_data_notice'], 'chưa có dữ liệu'), 'Explicitly notes missing data');

// 8. Context-aware boost
$ctx = may_retrieve_evidence('nghề này', ['page_id' => 'career', 'entity_id' => '1']);
expect_knowledge(count($ctx['items']) > 0, 'Retrieved items with page context');

echo "✓ All Mây Knowledge Retrieval Tests passed successfully!\n";
