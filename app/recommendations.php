<?php
declare(strict_types=1);

/** Serialize profile writes per user; no network calls while this lock is held. */
function recommendations_lock_user(int $userId): void
{
    if (!db()->inTransaction()) throw new LogicException('Profile lock requires a transaction.');
    $sql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'UPDATE users SET id=id WHERE id=?'
        : 'SELECT id FROM users WHERE id=? FOR UPDATE';
    $s = db()->prepare($sql);
    $s->execute([$userId]);
    $check = db()->prepare('SELECT id FROM users WHERE id=?');
    $check->execute([$userId]);
    if (!$check->fetchColumn()) throw new InvalidArgumentException('Không tìm thấy học sinh.');
}

function recommendations_json(?string $json): array
{
    $value = json_decode($json ?? '{}', true);
    return is_array($value) ? $value : [];
}

/** All available instruments are recorded; only the existing ranking inputs are scored. */
function recommendations_sources(array $psych): array
{
    $sources = [];
    foreach (['big5'=>'Big Five', 'caas'=>'CAAS', 'mbti'=>'MBTI', 'eq'=>'EQ'] as $type=>$label) {
        $row = $psych[$type] ?? null;
        $role = $row ? ($type === 'big5' ? 'ranking' : 'reflection') : 'missing';
        if ($row && $type === 'big5' && ($row['validity_flag'] ?? 'valid') !== 'valid') $role = 'review_needed';
        $sources[$type] = ['label'=>$label, 'id'=>$row ? (int)$row['id'] : null,
            'completed_at'=>$row['completed_at'] ?? null, 'role'=>$role,
            'validity_flag'=>$row['validity_flag'] ?? null];
    }
    return $sources;
}

/** Use saved scores, not today's question bank, to refresh a completed assessment. */
function recommendations_rebuild_locked(int $userId, ?int $assessmentId = null): ?array
{
    if (!db()->inTransaction()) throw new LogicException('Refresh requires a transaction.');
    $sql = 'SELECT * FROM assessments WHERE user_id=?';
    $args = [$userId];
    if ($assessmentId !== null) { $sql .= ' AND id=?'; $args[] = $assessmentId; }
    $s = db()->prepare($sql . ' ORDER BY completed_at DESC,id DESC LIMIT 1');
    $s->execute($args);
    $assessment = $s->fetch();
    // Do not silently reuse a previous Holland when the latest is still in progress.
    if (!$assessment || empty($assessment['constraint_answers'])) return null;
    $scores = recommendations_json($assessment['scores']);
    if (!$scores) return null;
    $metrics = recommendations_json($assessment['validity_metrics'] ?? null);
    $psych = four_hub_latest_psychometrics($userId);
    $sources = recommendations_sources($psych);
    $ipsative = [];
    foreach (recommendations_json($assessment['ipsative_z_scores'] ?? null) as $axis=>$z) {
        if (is_numeric($z) && array_key_exists($axis, riasec_labels())) {
            $ipsative[$axis] = max(5, min(95, (int)round(50 + 15 * (float)$z)));
        }
    }
    $efficacy = recommendations_json($assessment['efficacy_scores'] ?? null);
    $constraints = recommendations_json($assessment['constraint_answers']);
    $big5 = $sources['big5']['role'] === 'ranking' ? ($psych['big5']['scores'] ?? []) : [];
    $matches = career_matches($scores, 6, $constraints, [
        'ipsative_scores'=>$ipsative, 'efficacy_scores'=>$efficacy, 'big5_scores'=>$big5,
    ]);
    $ranked = array_map(static fn(array $m): array => ['career_id'=>(int)$m['id'], 'score'=>(int)$m['match_percent']], $matches);
    $fingerprint = hash('sha256', json_encode([
        'version'=>1, 'assessment_id'=>(int)$assessment['id'], 'scores'=>$scores,
        'ipsative'=>$ipsative, 'efficacy'=>$efficacy, 'constraints'=>$constraints,
        'sources'=>$sources, 'big5'=>$big5, 'matches'=>$ranked,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $old = $metrics['recommendation_snapshot'] ?? [];
    if (($old['fingerprint'] ?? '') === $fingerprint) return $old;
    $snapshot = ['version'=>1, 'assessment_id'=>(int)$assessment['id'], 'fingerprint'=>$fingerprint,
        'updated_at'=>date('Y-m-d H:i:s'), 'sources'=>$sources,
        'ranking_basis'=>['Holland', 'Điều kiện đã khai', ...($big5 ? ['Big Five'] : [])]];
    $metrics['recommendation_snapshot'] = $snapshot;
    db()->prepare('DELETE FROM assessment_matches WHERE assessment_id=?')->execute([$assessment['id']]);
    $insert = db()->prepare('INSERT INTO assessment_matches(assessment_id,career_id,match_percent,rank_order) VALUES(?,?,?,?)');
    foreach ($matches as $i=>$match) $insert->execute([$assessment['id'], $match['id'], $match['match_percent'], $i+1]);
    // Old AI prose refers to an older profile and must not accompany the refreshed ranking.
    db()->prepare("UPDATE assessments SET validity_metrics=?,ai_advice=NULL,ai_status='pending',ai_model=NULL,ai_error=NULL,ai_generated_at=NULL,discrepancy_report=NULL WHERE id=? AND user_id=?")
        ->execute([json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $assessment['id'], $userId]);
    return $snapshot;
}

function recommendations_refresh_current(int $userId): ?array
{
    db()->beginTransaction();
    try {
        recommendations_lock_user($userId);
        $result = recommendations_rebuild_locked($userId);
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/** Saving an instrument and refreshing matches succeed or roll back together. */
function recommendations_save_psychometric(int $userId, string $type, array $answers, int $duration): array
{
    require_once __DIR__.'/psychometrics.php';
    $scored = score_psychometric($type, $answers, $duration);
    db()->beginTransaction();
    try {
        recommendations_lock_user($userId);
        $s = db()->prepare('INSERT INTO user_psychometrics(user_id,test_type,answers_json,scores_json,result_code,summary_label,duration_seconds,validity_flag) VALUES(?,?,?,?,?,?,?,?)');
        $s->execute([$userId, $type, json_encode($scored['answers'], JSON_UNESCAPED_UNICODE),
            json_encode($scored['scores'], JSON_UNESCAPED_UNICODE), $scored['result_code'],
            $scored['summary_label'], $duration, $scored['validity_flag'] ?? 'valid']);
        $scored['recommendation_snapshot'] = recommendations_rebuild_locked($userId);
        db()->commit();
        return $scored;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/** Token for rejecting an AI response if a profile changed during the network request. */
function recommendations_ai_token(int $userId, int $assessmentId): string
{
    $s = db()->prepare('SELECT scores,constraint_answers,efficacy_scores,ipsative_z_scores,validity_metrics FROM assessments WHERE id=? AND user_id=?');
    $s->execute([$assessmentId, $userId]);
    $row = $s->fetch();
    if (!$row) throw new InvalidArgumentException('Không tìm thấy kết quả đánh giá.');
    // Decode JSON so MySQL's formatting normalization does not affect equality.
    $row = array_map('recommendations_json', $row);
    $latest = db()->prepare('SELECT id FROM assessments WHERE user_id=? ORDER BY completed_at DESC,id DESC LIMIT 1');
    $latest->execute([$userId]);
    return hash('sha256', json_encode([$row, $latest->fetchColumn(), recommendations_sources(four_hub_latest_psychometrics($userId))], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function recommendations_publish_ai(int $userId, int $assessmentId, string $token, callable $publish): bool
{
    db()->beginTransaction();
    try {
        recommendations_lock_user($userId);
        if (!hash_equals($token, recommendations_ai_token($userId, $assessmentId))) {
            db()->rollBack();
            return false;
        }
        $publish();
        db()->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
