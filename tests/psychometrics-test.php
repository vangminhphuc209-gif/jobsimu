<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/psychometrics.php';
function check($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

// 1. Test question counts, neutral midpoint normalization, and malformed input rejection across all 4 instruments
foreach (['mbti'=>12, 'eq'=>10, 'big5'=>20, 'caas'=>12] as $type=>$count) {
    $bank = psychometric_questions($type);
    check(count($bank)===$count, "Question count for $type");
    $neutral = array_fill_keys(array_keys($bank), 3);
    $result = score_psychometric($type, $neutral, 60);
    if ($type==='mbti') {
        check($result['result_code']==='XXXX', 'Balanced answers must not force a type');
    } else {
        foreach ($result['scores'] as $score) check($score===50, "Neutral $type normalizes independently per pillar to 50");
    }
    foreach ([[], array_merge($neutral,['unexpected'=>3]), array_replace($neutral,[array_key_first($bank)=>'2.5']), array_replace($neutral,[array_key_first($bank)=>true]), array_replace($neutral,[array_key_first($bank)=>['3']]), array_replace($neutral,[array_key_first($bank)=>6])] as $bad) {
        try { score_psychometric($type, $bad); throw new RuntimeException("Invalid answers accepted for $type"); }
        catch (InvalidArgumentException $expected) {}
    }
}

// 2. Legacy MBTI & EQ extremes
$bank=psychometric_questions('mbti');$first=[];$opposite=[];
foreach($bank as $key=>$q){$first[$key]=$q['pole']===$q['axis'][0]?5:1;$opposite[$key]=6-$first[$key];}
check(score_psychometric('mbti',$first)['result_code']==='ESTJ','Reverse keyed extremes');
check(score_psychometric('mbti',$opposite)['result_code']==='INFP','Opposite extremes');
foreach([1=>0,5=>100] as $value=>$expected){$r=score_psychometric('eq',array_fill_keys(array_keys(psychometric_questions('eq')),$value));foreach($r['scores'] as $score)check($score===$expected,'EQ endpoint normalization');}

// 3. Scientific Big Five (Mini-IPIP) & CAAS reverse-keyed scoring & quality validation checks
foreach (['big5', 'caas'] as $sciType) {
    $sBank = psychometric_questions($sciType);
    $maxKeyed = [];
    $minKeyed = [];
    $flatFive = [];
    foreach ($sBank as $k => $q) {
        $isPos = ($q['pole'] ?? '+') === '+';
        $maxKeyed[$k] = $isPos ? 5 : 1;
        $minKeyed[$k] = $isPos ? 1 : 5;
        $flatFive[$k] = 5; // Acquiescence / Contradictory responding (answering 5 to both + and - items)
    }
    $resMax = score_psychometric($sciType, $maxKeyed, 90);
    foreach ($resMax['scores'] as $axis => $sc) {
        check($sc === 100, "$sciType max keyed score for $axis must be 100, got $sc");
    }
    check($resMax['validity_flag'] === 'valid', "$sciType coherent answers must be valid");

    $resMin = score_psychometric($sciType, $minKeyed, 90);
    foreach ($resMin['scores'] as $axis => $sc) {
        check($sc === 0, "$sciType min keyed score for $axis must be 0, got $sc");
    }

    // Flatline 5s on a balanced +/- scale should cancel out to 50 and trigger 'inconsistent' validity flag
    $resFlat = score_psychometric($sciType, $flatFive, 90);
    check($resFlat['validity_flag'] === 'inconsistent', "$sciType flat 5s across reverse-keyed items must flag inconsistent");

    // Rushed completion (< 1.2s per item) should trigger 'rushed' validity flag
    $resRushed = score_psychometric($sciType, $maxKeyed, 5);
    check($resRushed['validity_flag'] === 'rushed', "$sciType 5-second completion must flag rushed");
}

echo "Psychometric scoring checks passed (MBTI, EQ, Mini-IPIP Big Five, CAAS, and Validity Guards).\n";
