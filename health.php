<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_admin(); }
$checks=[];
function health_check(string $name,bool $ok,string $detail):void{global $checks;$checks[]=compact('name','ok','detail');}
health_check('PHP 7.4+',version_compare(PHP_VERSION,'7.4.0','>='),PHP_VERSION);
health_check('PDO MySQL',extension_loaded('pdo_mysql'),'Kết nối database MySQL/MariaDB');
health_check('PHP cURL',extension_loaded('curl'),'Gọi OpenAI Responses API');
health_check('Mbstring',extension_loaded('mbstring'),'Xử lý tiếng Việt Unicode');
health_check('HTTPS',(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https','Khuyến nghị bắt buộc ở production');
foreach(['users','questions','careers','assessments','assessment_matches','favorites','simulation_attempts','settings','audit_logs','ai_logs','knowledge_articles','external_portals','user_psychometrics','ai_chat_messages','career_groups','majors','institutions','programs','admission_records'] as $table){try{$count=(int)db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();health_check('Bảng '.$table,true,number_format($count).' bản ghi');}catch(Throwable $e){health_check('Bảng '.$table,false,$e->getMessage());}}
$questionCount=(int)db()->query("SELECT COUNT(*) FROM questions WHERE status='active'")->fetchColumn();health_check('Bộ câu hỏi RIASEC',$questionCount===30,$questionCount.' câu đang hoạt động (khuyến nghị đúng 30)');
$careerCount=(int)db()->query("SELECT COUNT(*) FROM careers WHERE status='published'")->fetchColumn();health_check('Thư viện nghề',$careerCount>=15,$careerCount.' nghề công khai');
health_check('OpenAI API key',setting('openai_api_key')!=='',setting('openai_api_key')?'Đã cấu hình':'Chưa cấu hình — hệ thống dùng tư vấn dự phòng');
$allOk=!in_array(false,array_column($checks,'ok'),true);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Kiểm tra hệ thống</title><link rel="stylesheet" href="<?=url('assets/css/app.css')?>"></head><body><div class="container py-12"><div class="mb-8 flex flex-wrap items-center justify-between gap-4"><div><span class="eyebrow">Production Health Check</span><h1 class="mt-3 text-4xl font-black">Trạng thái hệ thống</h1><p class="muted">Kiểm tra môi trường, database, dữ liệu mẫu và tích hợp cần thiết.</p></div><a class="btn btn-outline" href="<?=url('admin.php')?>">← Quay lại Admin</a></div><div class="alert <?=$allOk?'alert-success':'alert-warning'?>"><?=$allOk?'✓ Các kiểm tra bắt buộc đều đạt.':'Một số mục cần được xử lý trước khi đưa vào sử dụng chính thức.'?></div><div class="grid gap-4 md:grid-cols-2"><?php foreach($checks as $c):?><div class="card flex items-center gap-4"><div class="grid h-11 w-11 flex-none place-items-center rounded-full <?=$c['ok']?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'?> text-xl font-black"><?=$c['ok']?'✓':'!'?></div><div><b><?=e($c['name'])?></b><p class="text-sm"><?=e($c['detail'])?></p></div></div><?php endforeach;?></div></div></body></html>
