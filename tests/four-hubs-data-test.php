<?php
declare(strict_types=1);
define('ROOT_PATH',dirname(__DIR__));
require ROOT_PATH.'/app/functions.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,grade TEXT); INSERT INTO users VALUES(1,'Lớp 10'),(2,'Lớp 11'); CREATE TABLE settings(setting_key TEXT,setting_value TEXT); CREATE TABLE assessments(id INTEGER PRIMARY KEY,user_id INTEGER,scores TEXT,result_code TEXT,constraint_answers TEXT,completed_at TEXT); CREATE TABLE careers(id INTEGER PRIMARY KEY,title TEXT,slug TEXT,subjects TEXT,skills TEXT,education_path TEXT,status TEXT); CREATE TABLE assessment_matches(assessment_id INTEGER,career_id INTEGER,match_percent INTEGER,rank_order INTEGER); CREATE TABLE simulation_attempts(id INTEGER PRIMARY KEY,user_id INTEGER,career_id INTEGER,skill_scores TEXT,reflection_feeling TEXT,reflection_note TEXT,completed_at TEXT)");
function expect_hub(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
run_four_hub_migrations();run_four_hub_migrations();
expect_hub((int)$pdo->query('SELECT COUNT(*) FROM knowledge_articles')->fetchColumn()===3,'Idempotent article seed');
expect_hub((int)$pdo->query('SELECT COUNT(*) FROM external_portals')->fetchColumn()===2,'Idempotent portals');
$pdo->exec("UPDATE knowledge_articles SET title='Admin edit' WHERE id=1");run_four_hub_migrations();
expect_hub($pdo->query('SELECT title FROM knowledge_articles WHERE id=1')->fetchColumn()==='Admin edit','Preserve editorial edits');
$pdo->exec("INSERT INTO user_psychometrics(user_id,test_type,answers_json,scores_json,result_code,summary_label) VALUES (1,'mbti','{}','{}','ESTJ','first'),(1,'mbti','{}','{}','INFP','latest'),(2,'eq','{}','{}','EQ','private')");
expect_hub(four_hub_latest_psychometrics(1)['mbti']['result_code']==='INFP','Stable latest result');
expect_hub(!isset(four_hub_latest_psychometrics(1)['eq']),'No cross-user psychometric leak');
$pdo->exec("INSERT INTO careers VALUES(1,'Teacher','giao-vien','Toán','Giao tiếp','Đại học','published'); INSERT INTO simulation_attempts VALUES(1,1,1,'{\"analysis\":85,\"precision\":120}','excited','hello','2026-11-08 09:00:00'),(2,2,1,'{}','unsuited','private','2026-11-08 10:00:00')");
$activity=four_hub_activity(1);expect_hub(count($activity)===1&&$activity[0]['skills']['precision']===100,'Activity isolation and bounds');
$pdo->exec("CREATE TABLE game_runs(id TEXT,career_id TEXT,status TEXT,updated_at TEXT,user_id INTEGER); CREATE TABLE game_reports(run_id TEXT,summary_json TEXT,completed_at TEXT); INSERT INTO game_runs VALUES('r1','giao-vien','completed','2026-11-08 11:00:00',1),('r2','giao-vien','completed','2026-11-08 12:00:00',2); INSERT INTO game_reports VALUES('r1','{\"competencies\":{\"empathy\":\"Observed listening\"}}','2026-11-08 11:00:00')");
$activity=four_hub_activity(1);expect_hub(count($activity)===2&&$activity[0]['mode']==='3D','Unified timeline sorting');
expect_hub($activity[0]['skills']===[],'3D is not reduced to score');
$context=four_hub_ai_context(1);expect_hub(count($context['experiences'])===2,'AI includes both modes');
for($i=0;$i<10;$i++)four_hub_reserve_chat(1,'Question', $context);
try{four_hub_reserve_chat(1,'Extra',$context);throw new RuntimeException('Quota bypass');}catch(RuntimeException $e){expect_hub($e->getCode()===429,'Quota rejects 11th message');}
expect_hub(four_hub_chat_count(2)===0,'Quota per user');
expect_hub(four_hub_chat_answer(1,1,$context,'Học môn gì?')['model']==='fallback','No-key fallback');
expect_hub(safe_external_url('javascript:alert(1)')==='','Unsafe URL rejected');
$pdo->exec('DELETE FROM users WHERE id=1');expect_hub(count(four_hub_latest_psychometrics(1))===0,'Psychometric cascade');
echo "Four-hub data checks passed: migration, isolation, timeline, quota, fallback, cascade.\n";
