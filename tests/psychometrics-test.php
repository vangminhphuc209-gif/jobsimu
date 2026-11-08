<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/psychometrics.php';
function check($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
foreach (['mbti'=>12,'eq'=>10] as $type=>$count) {
    $bank = psychometric_questions($type);
    check(count($bank)===$count, 'Question count');
    $neutral = array_fill_keys(array_keys($bank), 3);
    $result = score_psychometric($type, $neutral);
    if ($type==='mbti') check($result['result_code']==='XXXX', 'Balanced answers must not force a type');
    else foreach ($result['scores'] as $score) check($score===50, 'Neutral EQ normalizes independently per pillar');
    foreach ([[], array_merge($neutral,['unexpected'=>3]), array_replace($neutral,[array_key_first($bank)=>'2.5']), array_replace($neutral,[array_key_first($bank)=>true]), array_replace($neutral,[array_key_first($bank)=>['3']]), array_replace($neutral,[array_key_first($bank)=>6])] as $bad) {
        try { score_psychometric($type, $bad); throw new RuntimeException('Invalid answers accepted'); }
        catch (InvalidArgumentException $expected) {}
    }
}
$bank=psychometric_questions('mbti');$first=[];$opposite=[];
foreach($bank as $key=>$q){$first[$key]=$q['pole']===$q['axis'][0]?5:1;$opposite[$key]=6-$first[$key];}
check(score_psychometric('mbti',$first)['result_code']==='ESTJ','Reverse keyed extremes');
check(score_psychometric('mbti',$opposite)['result_code']==='INFP','Opposite extremes');
foreach([1=>0,5=>100] as $value=>$expected){$r=score_psychometric('eq',array_fill_keys(array_keys(psychometric_questions('eq')),$value));foreach($r['scores'] as $score)check($score===$expected,'EQ endpoint normalization');}
echo "Psychometric scoring checks passed.\n";
