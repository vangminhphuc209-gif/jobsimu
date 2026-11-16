"""Exercise real PHP routes against an isolated SQLite database. Never uses config.php."""
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import requests

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')

with tempfile.TemporaryDirectory(prefix='jobsimu-recommendations-') as temp:
    dbpath = str(Path(temp) / 'fixture.sqlite')
    db = sqlite3.connect(dbpath)
    # Translate only the legacy bootstrap fixture; application migrations run normally.
    sql = (ROOT / 'database/schema.sql').read_text()
    sql = re.sub(r'(?:BIGINT|INT) UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', sql)
    sql = sql.replace(' UNSIGNED', '').replace(' ON UPDATE CURRENT_TIMESTAMP', '')
    sql = re.sub(r"ENUM\([^)]*\)", 'TEXT', sql)
    sql = re.sub(r',\s*INDEX\([^)]*\)', '', sql)
    sql = sql.replace('UNIQUE KEY uk_content (content(190))', 'UNIQUE(content)')
    sql = re.sub(r' ENGINE=InnoDB[^;]*', '', sql)
    db.executescript(sql)
    password = subprocess.check_output([PHP, '-r', "echo password_hash('TestPassword123', PASSWORD_DEFAULT);"], text=True)
    for user_id in [1, 2]:
        db.execute('INSERT INTO users(id,name,email,password,grade) VALUES(?,?,?,?,?)',
                   (user_id, 'Student', f'student{user_id}@example.test', password, 'Lớp 11'))
    for i, axis in enumerate('RIASEC'):
        for j in range(5):
            db.execute('INSERT INTO questions(content,riasec_type,sort_order) VALUES(?,?,?)', (f'Question {i}-{j}', axis, i*5+j))
    db.commit()
    sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close()
    base = f'http://127.0.0.1:{port}'
    env = {**os.environ, 'JOBSIMU_FORCE_ENV_DB':'1', 'DB_DRIVER':'sqlite', 'DB_PATH':dbpath,
           'DB_NAME':'fixture', 'APP_URL':base, 'APP_DEBUG':'1', 'SESSION_NAME':'recommendation_test'}
    log = open(Path(temp) / 'server.log', 'w+')
    proc = subprocess.Popen([PHP, '-d', f'session.save_path={temp}', '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=log, stderr=log)
    session = requests.Session(); session.trust_env = False
    def token(response):
        return re.search(r'name="csrf" value="([^"]+)"', response.text)[1]
    def get(page):
        r = session.get(base+'/index.php', params={'page':page}, timeout=20)
        assert r.status_code == 200, (page, r.status_code, r.text[:600])
        assert not re.search(r'Fatal error|Warning:|Parse error', r.text), r.text[:1200]
        return r
    def snapshot():
        return json.loads(db.execute('SELECT validity_metrics FROM assessments WHERE user_id=1 ORDER BY id DESC LIMIT 1').fetchone()[0])['recommendation_snapshot']
    try:
        for _ in range(60):
            try:
                response = session.get(base+'/index.php?page=login', timeout=2)
                if response.status_code == 200: break
            except requests.RequestException: pass
            time.sleep(.1)
        else: raise AssertionError('PHP server did not become ready')
        r = session.post(base+'/index.php?page=login', data={'csrf':token(response), 'email':'student1@example.test','password':'TestPassword123'}, timeout=20)
        assert r.status_code == 200 and 'Xin chào' in r.text, r.text[:800]
        assert 'Các bài bạn đã làm vẫn được lưu' in get('assessment-report').text
        # Real Holland -> conditions -> result flow.
        r = get('assessment')
        answers = {f'answers[{i+1}]':str([5,4,2,1,2,3][i//5]) for i in range(30)}
        r = session.post(base+'/index.php?page=assessment', data={'csrf':token(r),'duration_seconds':120,**answers}, timeout=20)
        assert 'constraints' in r.url, r.text[:800]
        assessment_id = db.execute('SELECT MAX(id) FROM assessments').fetchone()[0]
        r = session.post(base+f'/index.php?page=constraints&id={assessment_id}', data={'csrf':token(r),'skip_survey':1}, timeout=20)
        assert r.status_code == 200 and 'Fatal error' not in r.text, r.text[:1600]
        first = snapshot()
        assert first['sources']['big5']['role'] == 'missing'
        for typ in ['big5','caas','mbti','eq','big5']:
            r = get('test-'+typ)
            keys = list(dict.fromkeys(re.findall(r'name="(answers\[[^]]+\])"', r.text)))
            # Use the actual question bank's polarity for a coherent Big Five response.
            php = "require 'app/psychometrics.php'; echo json_encode(psychometric_questions('"+typ+"'));"
            bank = json.loads(subprocess.check_output([PHP,'-r',php], cwd=ROOT, text=True))
            data = {f'answers[{key}]':(5 if q.get('pole','+')=='+' else 1) for key,q in bank.items()}
            r = session.post(base+'/index.php?page=test-'+typ, data={'csrf':token(r),'duration_seconds':90,**data}, timeout=20)
            assert r.status_code == 200 and 'Gợi ý từ hồ sơ hiện tại' in r.text, r.text[:1400]
            assert 'Fatal error' not in r.text and 'Warning:' not in r.text
            current = snapshot()
            assert current['fingerprint'] != first['fingerprint'], typ
            assert current['sources'][typ]['id'] is not None
            first = current
        assert snapshot()['sources']['big5']['role'] == 'ranking'
        assert db.execute('SELECT ai_advice FROM assessments WHERE id=?',(assessment_id,)).fetchone()[0] is None
        r = session.get(base+f'/index.php?page=result&id={assessment_id}', timeout=20)
        assert 'Phần tư vấn AI cần được tạo lại' in r.text
        count = db.execute('SELECT COUNT(*) FROM user_psychometrics').fetchone()[0]
        r = session.post(base+'/index.php?page=test-eq', data={'answers[e01]':3}, timeout=20)
        assert r.status_code == 419
        r = get('test-eq')
        r = session.post(base+'/index.php?page=test-eq', data={'csrf':token(r),'answers[e01]':3}, timeout=20)
        assert r.status_code == 422
        assert db.execute('SELECT COUNT(*) FROM user_psychometrics').fetchone()[0] == count
        assert db.execute('SELECT COUNT(*) FROM user_psychometrics WHERE user_id=2').fetchone()[0] == 0
        guest = requests.Session(); guest.trust_env = False
        r = guest.post(base+'/index.php?page=test-big5', data={'user_id':1}, allow_redirects=False, timeout=20)
        assert r.status_code == 302 and 'login' in r.headers['Location']
        print('Recommendation HTTP checks passed: Holland/conditions, all four tests, retake, report/result, stale advice, CSRF, validation and authentication.')
    finally:
        proc.terminate(); proc.wait(timeout=10)
        log.seek(0)
        if proc.returncode not in (0, -15): print(log.read()[-3000:])
        log.close(); db.close()
