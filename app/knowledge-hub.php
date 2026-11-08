<?php
declare(strict_types=1);

$articleCategories = ['admissions' => 'Tuyển sinh', 'trend' => 'Xu hướng nghề', 'guide' => 'Cẩm nang'];
$articleSlug = is_string($_GET['article'] ?? null) ? trim($_GET['article']) : '';
if ($articleSlug !== '') {
    $stmt = db()->prepare("SELECT * FROM knowledge_articles WHERE slug=? AND status='published'");
    $stmt->execute([$articleSlug]);
    $article = $stmt->fetch();
    if (!$article) http_response_code(404);
    $pageTitle = $article ? $article['title'] : 'Không tìm thấy bài viết';
    require __DIR__ . '/header.php';
    ?>
    <div class="container"><a class="btn btn-outline" href="<?=url('index.php?page=knowledge-hub')?>">← Kiến thức & Tuyển sinh</a>
    <?php if (!$article): ?><div class="card empty"><h1>Bài viết không còn được công khai</h1><p>Hãy quay lại trung tâm kiến thức để chọn nội dung khác.</p></div>
    <?php else: ?>
    <article class="card"><span class="eyebrow"><?=e($articleCategories[$article['category']] ?? 'Cẩm nang')?></span><h1><?=e($article['title'])?></h1><p class="muted">Cập nhật <?=e(date('d/m/Y', strtotime($article['updated_at'])))?></p><p><strong><?=e($article['summary'])?></strong></p><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?=e($article['content'])?></div>
    <?php $source = safe_external_url($article['source_url']); if ($source !== ''): ?><p>Nguồn tham khảo: <a href="<?=e($source)?>" target="_blank" rel="noopener noreferrer"><?=e($article['source_name'] ?: 'Xem nguồn')?> ↗</a> <small class="muted">Mở website bên ngoài trong tab mới.</small></p><?php endif; ?>
    <?php if ($article['related_career_slug']): $stmt=db()->prepare("SELECT id,title FROM careers WHERE slug=? AND status='published'");$stmt->execute([$article['related_career_slug']]);$related=$stmt->fetch();if($related): ?><a class="btn" href="<?=url('index.php?page=career&id='.(int)$related['id'])?>">Khám phá nghề: <?=e($related['title'])?> →</a><?php endif;endif; ?>
    </article><?php endif; ?></div>
    <?php require __DIR__ . '/footer.php'; exit;
}
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!array_key_exists($type, riasec_labels())) $type = '';
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
if (!array_key_exists($category, $articleCategories)) $category = '';
$stmt=db()->prepare("SELECT * FROM knowledge_articles WHERE status='published'" . ($category ? ' AND category=?' : '') . ' ORDER BY featured DESC,updated_at DESC,id DESC LIMIT 24');
$stmt->execute($category ? [$category] : []);$articles=$stmt->fetchAll();
$stmt=db()->prepare("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC,title");$stmt->execute();$allCareers=$stmt->fetchAll();
$requirements = career_requirements_catalog();
$groups = array_values(array_unique(array_column($requirements, 'group'))); sort($groups);
$group = is_string($_GET['group'] ?? null) && in_array($_GET['group'], $groups, true) ? $_GET['group'] : '';
$careers=array_filter($allCareers, static function(array $career) use ($q,$type,$group,$requirements): bool {
    if ($group !== '' && ($requirements[$career['slug']]['group'] ?? '') !== $group) return false;
    if ($q !== '' && mb_stripos($career['title'].' '.$career['short_description'].' '.$career['subjects'],$q) === false) return false;
    $weights=json_decode($career['riasec_weights'],true);if(!is_array($weights))$weights=[];
    arsort($weights);
    return $type === '' || in_array($type,array_slice(array_keys($weights),0,2),true);
});
$stmt=db()->prepare("SELECT * FROM external_portals WHERE status='active' ORDER BY sort_order,id");$stmt->execute();$portals=$stmt->fetchAll();
$pageTitle='Kiến thức & Tuyển sinh';require __DIR__.'/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">01 · Hiểu nghề trước khi chọn nghề</span><h1>Kiến thức & Tuyển sinh</h1><p>Tìm hiểu công việc, đường học và nguồn thông tin để tự xây dựng lựa chọn của bạn.</p><nav aria-label="Các phần của trung tâm"><a class="btn" href="#articles">Tin tức & Cẩm nang</a> <a class="btn" href="#careers">Thư viện nghề</a> <a class="btn" href="#portals">Cổng thông tin uy tín</a></nav></div></section>
<div class="container">
<section id="articles"><h2>Tuyển sinh & Xu hướng nghề nghiệp</h2><p class="muted">Kiểm tra ngày cập nhật và thông báo từ cơ sở đào tạo trước khi nộp hồ sơ. Các bài cẩm nang không thay thế quy chế tuyển sinh chính thức.</p>
<form method="get" class="toolbar"><input type="hidden" name="page" value="knowledge-hub"><label for="articleCategory">Chuyên mục</label><select name="category" id="articleCategory"><option value="">Tất cả</option><?php foreach($articleCategories as $key=>$label): ?><option value="<?=e($key)?>" <?=$key===$category?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select><button class="btn btn-outline">Lọc bài viết</button></form>
<div class="grid-3"><?php foreach($articles as $article): ?><article class="card"><span class="tag"><?=e($articleCategories[$article['category']] ?? 'Cẩm nang')?></span><h3><a href="<?=e(url('index.php?page=knowledge-hub&article='.rawurlencode($article['slug'])))?>"><?=e($article['title'])?></a></h3><p><?=e($article['summary'])?></p><small class="muted"><?=e(date('d/m/Y',strtotime($article['updated_at'])))?></small><p><a class="btn btn-outline" href="<?=e(url('index.php?page=knowledge-hub&article='.rawurlencode($article['slug'])))?>">Đọc bài viết →</a></p></article><?php endforeach; ?></div>
<?php if(!$articles): ?><div class="card empty">Chưa có bài viết công khai trong chuyên mục này.</div><?php endif; ?></section>
<section id="careers"><h2>Thư viện nghề & Lộ trình học tập</h2><p class="muted"><?=count($allCareers)?> hồ sơ nghề đang công khai. Bộ lọc RIASEC chọn các nghề có nhóm này nằm trong hai sở thích nổi bật; đây là gợi ý khám phá, không phải điều kiện tuyển sinh.</p>
<form method="get" class="toolbar"><input type="hidden" name="page" value="knowledge-hub"><div class="form-group"><label for="careerQuery">Từ khóa nghề hoặc môn học</label><input class="input" id="careerQuery" name="q" maxlength="100" value="<?=e($q)?>" placeholder="Ví dụ: giáo viên, toán, thiết kế"></div><div class="form-group"><label for="careerType">Nhóm sở thích RIASEC</label><select id="careerType" name="type"><option value="">Tất cả nhóm</option><?php foreach(riasec_labels() as $key=>$label): ?><option value="<?=e($key)?>" <?=$key===$type?'selected':''?>><?=e($key.' · '.$label)?></option><?php endforeach; ?></select></div><div class="form-group"><label for="careerGroup">Nhóm ngành</label><select id="careerGroup" name="group"><option value="">Tất cả nhóm ngành</option><?php foreach($groups as $label):?><option value="<?=e($label)?>" <?=$group===$label?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><button class="btn btn-primary">Tìm nghề</button><a class="btn btn-outline" href="<?=url('index.php?page=knowledge-hub#careers')?>">Xóa bộ lọc</a></form>
<p role="status"><?=count($careers)?> nghề phù hợp bộ lọc</p><div class="grid-3"><?php foreach($careers as $career): $weights=json_decode($career['riasec_weights'],true);if(!is_array($weights))$weights=[];arsort($weights); ?><a class="card career-card" href="<?=url('index.php?page=career&id='.(int)$career['id'])?>"><div class="career-icon"><?=e($career['icon'])?></div><h3><?=e($career['title'])?></h3><p><?=e($career['short_description'])?></p><div><?php foreach(array_slice(array_keys($weights),0,2) as $key): ?><span class="tag"><?=e(riasec_labels()[$key] ?? $key)?></span><?php endforeach; ?></div><p><small>Môn học: <?=e($career['subjects'])?></small></p><span>Chi tiết & Lộ trình học →</span></a><?php endforeach; ?></div>
<?php if(!$careers): ?><div class="card empty">Chưa tìm thấy nghề phù hợp. Thử từ khóa ngắn hơn hoặc chọn tất cả nhóm.</div><?php endif; ?></section>
<section id="portals"><h2>Cổng thông tin tham khảo</h2><p class="muted">Các liên kết sau đưa bạn ra ngoài JOBSIMU và mở trong tab mới. Hãy xác minh thông báo, thời hạn và đơn vị phát hành tại website đích.</p><div class="grid-3"><?php foreach($portals as $portal): $target=safe_external_url($portal['target_url']);if($target==='')continue; ?><article class="card"><div class="career-icon"><?=e($portal['icon'])?></div><h3><?=e($portal['title'])?></h3><p><strong><?=e($portal['organization'])?></strong></p><p><?=e($portal['description'])?></p><a class="btn btn-outline" href="<?=e($target)?>" target="_blank" rel="noopener noreferrer">Mở website bên ngoài ↗</a><p><small class="muted"><?=e(parse_url($target,PHP_URL_HOST))?> · Tab mới</small></p></article><?php endforeach; ?></div></section>
<div class="card"><h2>Đã có hình dung về thế giới nghề?</h2><p>Tiếp tục khám phá sở thích, phong cách học tập và cách bạn ứng xử trong các tình huống.</p><a class="btn btn-primary" href="<?=url('index.php?page=assessment-hub')?>">02 · Đánh giá bản thân →</a></div>
</div>
<?php require __DIR__.'/footer.php';exit;
