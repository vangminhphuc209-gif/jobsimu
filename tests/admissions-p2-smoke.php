<?php
declare(strict_types=1);
/** Run with php -d zend.assertions=1 -d assert.exception=1 tests/admissions-p2-smoke.php */
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){
    fwrite(STDERR,"SKIP: pdo_sqlite is unavailable; this is not a passed integration test.\n");
    exit(2);
}
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec("CREATE TABLE careers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,icon TEXT NOT NULL,short_description TEXT NOT NULL,
 description TEXT NOT NULL,work_environment TEXT,skills TEXT,subjects TEXT,education_path TEXT,
 riasec_weights TEXT NOT NULL,simulation_enabled INTEGER NOT NULL DEFAULT 0,
 featured INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'published')");
function db(): PDO {global $pdo;return $pdo;}
require __DIR__.'/../app/career-admissions.php';
require __DIR__.'/../app/admissions-p2.php';
require __DIR__.'/../app/admissions-p22.php';
function eq($expected,$actual,string $message): void {
    if($expected!==$actual)throw new RuntimeException($message.'; expected '.var_export($expected,true).', got '.var_export($actual,true));
}
run_admissions_migrations();
admissions_p2_migrate();
admissions_p22_migrate();
eq(8,admissions_p22_tuition_seed(),'8 sourced tuition bands');
eq(24,(int)$pdo->query('SELECT COUNT(*) FROM career_groups')->fetchColumn(),'24 groups');
eq(8,(int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn(),'8 new careers');
eq(5,(int)$pdo->query('SELECT COUNT(*) FROM institutions')->fetchColumn(),'5 starter institutions');
eq(1,(int)$pdo->query('SELECT COUNT(*) FROM programs')->fetchColumn(),'one sourced starter program');
run_admissions_migrations();
admissions_p2_migrate();
admissions_p22_migrate();
eq(0,admissions_p22_tuition_seed(),'tuition bands idempotent');
eq(8,(int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn(),'idempotent careers');
eq(5,(int)$pdo->query('SELECT COUNT(*) FROM institutions')->fetchColumn(),'idempotent institutions');
eq(6,(int)$pdo->query('SELECT COUNT(*) FROM admission_records')->fetchColumn(),'idempotent sourced cutoffs');
$template=array_fill_keys(admissions_p2_required_headers(),'');
$template=array_merge($template,[
 'type'=>'cutoff','institution_code'=>'TST','institution_name'=>'Trường thử nghiệm',
 'province'=>'Hà Nội','website'=>'https://example.edu.vn','major_code'=>'7480101',
 'major_name'=>'Khoa học máy tính','program_name'=>'Khoa học máy tính (chuẩn)',
 'program_code'=>'CS','year'=>'2025','method'=>'Điểm thi THPT','combination'=>'A00',
 'candidate_group'=>'','score'=>'25.50','score_scale'=>'30 điểm',
 'source_url'=>'https://example.edu.vn/2025','source_title'=>'Đề án tuyển sinh 2025'
]);
$clean=admissions_p2_validate_row($template,2);
eq('cutoff',$clean['type'],'valid cutoff row');
$bad=$template;$bad['source_url']='javascript:alert(1)';
try{admissions_p2_validate_row($bad,2);throw new RuntimeException('Accepted malicious source URL');}
catch(InvalidArgumentException $expected){}
$tuition=$template;
$tuition['type']='tuition';$tuition['year']='';$tuition['method']='';$tuition['score']='';
$tuition['academic_year']='2025-2026';$tuition['charge_basis']='academic_year';$tuition['amount_vnd']='25000000';
admissions_p2_validate_row($tuition,3);
$payload=json_encode([$template,$tuition],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$s=$pdo->prepare("INSERT INTO admission_import_batches(submitted_by,status,file_name,item_count,payload,created_at) VALUES(1,'pending','test.csv',2,?,?)");
$s->execute([$payload,date('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();
eq(2,admissions_p2_approve_batch($id,1),'approve count');
eq(1,(int)$pdo->query("SELECT COUNT(*) FROM tuition_records")->fetchColumn(),'tuition inserted');
eq(1,(int)$pdo->query("SELECT COUNT(*) FROM institutions WHERE code='TST'")->fetchColumn(),'school inserted');
eq(2,(int)$pdo->query("SELECT COUNT(*) FROM admission_source_documents WHERE batch_id=$id")->fetchColumn(),'sources tracked');
try{admissions_p2_approve_batch($id,1);throw new RuntimeException('Duplicate approval accepted');}
catch(InvalidArgumentException $expected){}
$growth=admissions_p2_tuition_growth(['academic_year'=>'2024-2025','charge_basis'=>'academic_year','amount_vnd'=>20000000],
 ['academic_year'=>'2025-2026','charge_basis'=>'academic_year','amount_vnd'=>22000000]);
eq(10.0,$growth,'tuition growth 10%');
eq(null,admissions_p2_tuition_growth(['academic_year'=>'2023-2024','charge_basis'=>'credit','amount_vnd'=>100],
 ['academic_year'=>'2025-2026','charge_basis'=>'academic_year','amount_vnd'=>200]),'noncomparable tuition');
$tmp=tempnam(sys_get_temp_dir(),'admissions-');
$fp=fopen($tmp,'wb');fwrite($fp,"\xEF\xBB\xBF".implode(',',admissions_p2_required_headers())."\n");
fputcsv($fp,array_values($template));fclose($fp);
eq(1,count(admissions_p2_read_csv($tmp)),'BOM csv import');
unlink($tmp);
$bands=$pdo->query("SELECT * FROM institution_tuition_bands WHERE program_group='Chương trình chuẩn' ORDER BY academic_year")->fetchAll();
eq(2,count($bands),'two years of standard tuition ranges');
$delta=admissions_p22_band_change($bands[0],$bands[1]);
eq(16.67,$delta['min_percent'],'historical lower bound growth');
eq(16.67,$delta['max_percent'],'historical upper bound growth');
echo "OK: P0 migrations and P2.1/P2.2 admissions smoke passed.\\n";
