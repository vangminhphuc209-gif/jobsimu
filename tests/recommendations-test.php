<?php
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH.'/app/functions.php';
require ROOT_PATH.'/app/psychometrics.php';

$dsn = getenv('RECOMMENDATIONS_TEST_DSN') ?: (in_array('sqlite', PDO::getAvailableDrivers(), true) ? 'sqlite::memory:' : null);
if ($dsn === null) {
    echo "SKIP: pdo_sqlite unavailable and RECOMMENDATIONS_TEST_DSN not set.\n";
    exit(0);
}
$pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: null, getenv('TEST_DB_PASS') ?: null,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
function expect_rec(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function matches_rec(int $id): array {
    $s=db()->prepare('SELECT career_id,match_percent,rank_order FROM assessment_matches WHERE assessment_id=? ORDER BY rank_order');
    $s->execute([$id]);return $s->fetchAll();
}
function answers_rec(string $type, bool $max): array {
    $a=[];foreach(psychometric_questions($type) as $k=>$q) {
        $positive=($q['pole']??'+')==='+';$a[$k]=$positive===$max?5:1;
    } return $a;
}
// Disposable test database only; never point this script at application data.
$pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY)');
$pdo->exec("CREATE TABLE assessments(id INTEGER PRIMARY KEY,user_id INTEGER, scores TEXT, constraint_answers TEXT, efficacy_scores TEXT,ipsative_z_scores TEXT,validity_metrics TEXT,completed_at TEXT,ai_advice TEXT,ai_status VARCHAR(30),ai_model TEXT,ai_error TEXT,ai_generated_at TEXT,discrepancy_report TEXT)");
$pdo->exec("CREATE TABLE user_psychometrics(id $pk,user_id INTEGER,test_type VARCHAR(20),answers_json TEXT,scores_json TEXT,result_code VARCHAR(40),summary_label TEXT,duration_seconds INTEGER,validity_flag VARCHAR(30),completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec('CREATE TABLE careers(id INTEGER PRIMARY KEY,title TEXT,slug VARCHAR(80),status VARCHAR(20),featured INTEGER,riasec_weights TEXT)');
$pdo->exec('CREATE TABLE assessment_matches(assessment_id INTEGER,career_id INTEGER,match_percent INTEGER,rank_order INTEGER,PRIMARY KEY(assessment_id,career_id))');
$pdo->exec('INSERT INTO users VALUES(1),(2)');
$insert=$pdo->prepare('INSERT INTO careers VALUES(?,?,?,?,?,?)');
$insert->execute([1,'Lập trình viên','lap-trinh-vien','published',0,json_encode(['R'=>20,'I'=>85,'A'=>20,'S'=>10,'E'=>15,'C'=>70])]);
$insert->execute([2,'Giáo viên','giao-vien','published',0,json_encode(['R'=>10,'I'=>55,'A'=>40,'S'=>90,'E'=>45,'C'=>40])]);
$insert->execute([3,'Nghề nháp','draft','draft',0,'{}']);
$baseScores=json_encode(['R'=>80,'I'=>70,'A'=>30,'S'=>20,'E'=>10,'C'=>50]);
$z=json_encode(['R'=>1.4,'I'=>0.8,'A'=>-0.6,'S'=>-1.0,'E'=>-1.4,'C'=>0.2]);
$stmt=$pdo->prepare('INSERT INTO assessments(id,user_id,scores,constraint_answers,efficacy_scores,ipsative_z_scores,validity_metrics,completed_at,ai_advice,ai_status) VALUES(?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([10,1,$baseScores,'{}','{}',$z,'{}','2026-11-01 10:00:00','historical','completed']);
$stmt->execute([20,1,$baseScores,'{"budget":"unknown"}','{}',$z,'{"validity_flag":"valid"}','2026-11-02 10:00:00','old prose','completed']);
$pdo->exec('INSERT INTO assessment_matches VALUES(10,1,44,1)');
$old=matches_rec(10);
$first=recommendations_refresh_current(1);
expect_rec($first['assessment_id']===20,'Refresh the latest assessment only');
expect_rec($first['sources']['big5']['role']==='missing','Missing Big Five stays missing');
$baseline=matches_rec(20);
expect_rec(count($baseline)===2,'Published careers only');
expect_rec($old===matches_rec(10),'Historical matches untouched');
expect_rec($pdo->query('SELECT ai_advice FROM assessments WHERE id=20')->fetchColumn()===null,'Invalidate old AI advice');
expect_rec(recommendations_json($pdo->query('SELECT validity_metrics FROM assessments WHERE id=20')->fetchColumn())['validity_flag']==='valid','Keep original validity metrics');

// Same inputs: do not invalidate advice or rewrite matches on each page view.
$pdo->exec("UPDATE assessments SET ai_advice='fresh',ai_status='completed' WHERE id=20");
$again=recommendations_refresh_current(1);
expect_rec($first===$again,'Refresh is idempotent');
expect_rec($pdo->query('SELECT ai_advice FROM assessments WHERE id=20')->fetchColumn()==='fresh','Unchanged profile preserves AI');

// Big Five after Holland changes matching, and retakes replace the input deterministically.
$high=recommendations_save_psychometric(1,'big5',answers_rec('big5',true),90);
$highMatches=matches_rec(20);
expect_rec($high['recommendation_snapshot']['sources']['big5']['role']==='ranking','Big Five participates');
expect_rec($highMatches!==$baseline,'New Big Five affects actual scores');
$low=recommendations_save_psychometric(1,'big5',answers_rec('big5',false),90);
expect_rec(matches_rec(20)!==$highMatches,'Retaken Big Five changes scores');
expect_rec($low['recommendation_snapshot']['sources']['big5']['id']>$high['recommendation_snapshot']['sources']['big5']['id'],'Newest row wins even in same second');
expect_rec($old===matches_rec(10),'Retake never overwrites older Holland');

// Question bank is deliberately absent: saved z scores, not current questions, drive refresh.
$ips=[];foreach(json_decode($z,true) as $k=>$v)$ips[$k]=max(5,min(95,(int)round(50+15*$v)));
$expected=career_matches(json_decode($baseScores,true),6,['budget'=>'unknown'],['ipsative_scores'=>$ips,'efficacy_scores'=>[], 'big5_scores'=>$low['scores']]);
expect_rec(array_map('intval',array_column(matches_rec(20),'match_percent'))===array_column($expected,'match_percent'),'Saved normalization and existing ranker are preserved');

// Reflection instruments update provenance and AI freshness, not invented ranking weights.
foreach(['caas','mbti','eq'] as $type) {
    $before=matches_rec(20);$result=recommendations_save_psychometric(1,$type,answers_rec($type,true),90);
    expect_rec($result['recommendation_snapshot']['sources'][$type]['role']==='reflection',"$type is reflection only");
    expect_rec(matches_rec(20)===$before,"$type does not invent rank changes");
}

// Flagged latest Big Five is visible but not silently reused or scored as a valid retake.
$rushed=recommendations_save_psychometric(1,'big5',answers_rec('big5',true),1);
expect_rec($rushed['recommendation_snapshot']['sources']['big5']['role']==='review_needed','Flagged input excluded explicitly');
expect_rec(matches_rec(20)===$baseline,'Excluded input restores missing-Big-Five baseline');

// Big Five before Holland: save succeeds; no fabricated result or cross-user effects.
$user1Before=matches_rec(20);
$without=recommendations_save_psychometric(2,'big5',answers_rec('big5',true),90);
expect_rec($without['recommendation_snapshot']===null,'No Holland means no invented ranking');
expect_rec(matches_rec(20)===$user1Before,'User isolation');
$stmt->execute([30,2,$baseScores,null,'{}',$z,'{}','2026-11-03 10:00:00',null,'pending']);
expect_rec(recommendations_refresh_current(2)===null,'Unfinished constraints do not publish matches');
$pdo->exec("UPDATE assessments SET constraint_answers='{}' WHERE id=30");
$user2=recommendations_refresh_current(2);
expect_rec($user2['sources']['big5']['role']==='ranking','Earlier Big Five is used after Holland');

// A new unfinished Holland must not mutate the previous completed result.
$stmt->execute([40,1,$baseScores,null,'{}',$z,'{}','2026-11-04 10:00:00',null,'pending']);
expect_rec(recommendations_save_psychometric(1,'eq',answers_rec('eq',false),90)['recommendation_snapshot']===null,'Latest incomplete assessment is not bypassed');
expect_rec(matches_rec(20)===$user1Before,'Previous assessment is history once a new one exists');

// AI responses generated against old inputs must never overwrite a refreshed profile.
$token=recommendations_ai_token(2,30);
recommendations_save_psychometric(2,'eq',answers_rec('eq',true),90);
$called=false;
expect_rec(!recommendations_publish_ai(2,30,$token,function()use(&$called){$called=true;}),'Reject stale AI');
expect_rec(!$called,'Stale publisher callback not executed');
$token=recommendations_ai_token(2,30);
expect_rec(recommendations_publish_ai(2,30,$token,function(){db()->exec("UPDATE assessments SET ai_advice='current' WHERE id=30");}),'Publish current AI');

// Fail while replacing matches: both the saved test and matches must roll back.
$beforeCount=(int)$pdo->query('SELECT COUNT(*) FROM user_psychometrics WHERE user_id=2')->fetchColumn();
$beforeMatches=matches_rec(30);
$beforeToken=recommendations_ai_token(2,30);
if ($mysql) $pdo->exec("CREATE TRIGGER fail_match BEFORE INSERT ON assessment_matches FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test write failure'");
else $pdo->exec("CREATE TRIGGER fail_match BEFORE INSERT ON assessment_matches BEGIN SELECT RAISE(ABORT,'test write failure'); END");
$failed=false;
try { recommendations_save_psychometric(2,'big5',answers_rec('big5',false),90); } catch(PDOException $e) { $failed=true; }
$pdo->exec('DROP TRIGGER fail_match');
expect_rec($failed,'Injected failure reached');
expect_rec((int)$pdo->query('SELECT COUNT(*) FROM user_psychometrics WHERE user_id=2')->fetchColumn()===$beforeCount,'Saved answer rolled back');
expect_rec(matches_rec(30)===$beforeMatches,'Previous matches restored');
expect_rec(recommendations_ai_token(2,30)===$beforeToken,'Provenance restored');
expect_rec(!$pdo->inTransaction(),'Transaction closed after failure');
echo "Recommendation refresh checks passed: order, retakes, provenance, quality, isolation, history, idempotency, stale AI and atomic rollback.\n";
