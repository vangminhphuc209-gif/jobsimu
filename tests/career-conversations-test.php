<?php
declare(strict_types=1);
require __DIR__.'/../app/career-conversations.php';
function expect_conversation(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$catalog = career_conversation_catalog();
expect_conversation(count($catalog)===9, 'Nine distinct scenarios');
expect_conversation(count(array_unique(array_column($catalog, 'career')))===3, 'Three careers');
foreach ($catalog as $id=>$row) {
    expect_conversation(career_conversation_select($id)===$row, 'Canonical server-side lookup');
    expect_conversation($row['kind']==='hypothetical' && $row['review_status']==='pilot_unreviewed', 'Honest pilot labels');
    expect_conversation(str_starts_with($row['source']['url'], 'https://www.onetonline.org/'), 'Allowlisted source');
    expect_conversation(count(career_conversation_prompts($row))===5, 'Conversation starters');
    $text=career_conversation_fallback($row);
    expect_conversation(str_contains($text,'chưa phân tích câu trả lời riêng') && str_contains($text,$row['source']['url']), 'Fallback is transparent and traceable');
}
foreach (['unknown', ['teacher-group'], '../config.php'] as $bad) {
    try { career_conversation_select($bad); throw new RuntimeException('Invalid selector accepted'); }
    catch (InvalidArgumentException $expected) {}
}
expect_conversation(career_conversation_select('')===null, 'General chat mode');
expect_conversation(career_website_help('XEM KẾT QUẢ HOLLAND')[0]['id']==='report','Vietnamese guide lookup');
expect_conversation(career_website_help('something unrelated')===[], 'No invented guide');
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    function db(): PDO {global $pdo;return $pdo;}
    $pdo->exec('CREATE TABLE ai_chat_messages(id INTEGER PRIMARY KEY,user_id INTEGER,role TEXT,message TEXT,model TEXT,created_at TEXT,context_snapshot TEXT)');
    $s=$pdo->prepare('INSERT INTO ai_chat_messages VALUES(?,?,?,?,?,?,?)');
    foreach ([[1,1,'user','general',null],[2,1,'user','teacher',['career_conversation'=>$catalog['teacher-group']]],[3,1,'assistant','teacher reply',['career_conversation'=>$catalog['teacher-group']]],[4,1,'user','developer',['career_conversation'=>$catalog['developer-bug']]],[5,2,'user','other user',['career_conversation'=>$catalog['teacher-group']]]] as [$id,$uid,$role,$message,$context]) {
        $s->execute([$id,$uid,$role,$message,'fallback','2026-11-16',json_encode($context)]);
    }
    expect_conversation(array_column(career_conversation_history(1,$catalog['teacher-group']),'message')===['teacher','teacher reply'],'Scenario isolation');
    expect_conversation(count(career_conversation_history(1,$catalog['teacher-group'],2))===1,'No future message in request');
    expect_conversation(array_column(career_conversation_history(1,null),'message')===['general'],'General history isolation');
}
echo "Career conversation checks passed: catalog, prompts, provenance, fallback, guide lookup, validation and user/scenario isolation.\n";
