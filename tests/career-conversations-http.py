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
        r = get('ai-roadmap')
        assert 'Góc người trong nghề' in r.text and r.text.count('Khám phá tình huống →') == 9
        for path in ['assessment-hub','assessment-report','majors','university-explorer']:
            assert get(path).status_code == 200
        def selected(scenario):
            r = session.get(base+'/index.php', params={'page':'ai-roadmap','scenario':scenario}, timeout=20)
            assert r.status_code == 200 and 'name="scenario"' in r.text
            return r
        def submit(r, scenario, message, **extra):
            nonce = re.search(r'name="chat_nonce" value="([^"]+)"', r.text)[1]
            payload = {'csrf':token(r), 'chat_nonce':nonce, 'scenario':scenario,'message':message,**extra}
            return session.post(base+'/index.php?page=ai-chat', data=payload, timeout=20), payload
        r = selected('teacher-group')
        r, payload = submit(r, 'teacher-group', 'Em sẽ hỏi từng bạn trước.')
        assert r.status_code == 200 and 'chưa phân tích câu trả lời riêng' in r.text
        assert 'scenario=teacher-group' in r.url
        count = db.execute('SELECT COUNT(*) FROM ai_chat_messages').fetchone()[0]
        assert count == 2
        contexts = [json.loads(x[0]) for x in db.execute('SELECT context_snapshot FROM ai_chat_messages')]
        assert all(x['career_conversation']['id']=='teacher-group' and 'psychometrics' not in x for x in contexts)
        session.post(base+'/index.php?page=ai-chat', data=payload, timeout=20)
        assert db.execute('SELECT COUNT(*) FROM ai_chat_messages').fetchone()[0] == count
        r = selected('developer-bug')
        assert 'Em sẽ hỏi từng bạn trước.' not in r.text
        r, _ = submit(r, 'developer-bug', '<script>alert(1)</script>', include_profile='1')
        assert r.status_code == 200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in r.text
        assert '<script>alert(1)</script>' not in r.text
        context = json.loads(db.execute('SELECT context_snapshot FROM ai_chat_messages ORDER BY id DESC LIMIT 1').fetchone()[0])
        assert 'psychometrics' in context
        r = get('ai-roadmap')
        assert 'Em sẽ hỏi từng bạn trước.' not in r.text
        r, _ = submit(r, '', 'Tôi xem kết quả ở đâu?')
        assert 'Hướng dẫn biên soạn sẵn từ website' in r.text
        r = session.get(base+'/index.php?page=ai-roadmap&scenario=unknown',timeout=20)
        assert r.status_code == 404
        r = session.get(base+'/index.php?page=ai-roadmap&scenario[]=teacher-group',timeout=20)
        assert r.status_code == 404
        r = session.post(base+'/index.php?page=ai-chat',data={'scenario':'teacher-group','message':'Hello'},timeout=20)
        assert r.status_code == 419
        assert db.execute('SELECT COUNT(*) FROM ai_chat_messages WHERE user_id=2').fetchone()[0] == 0
        guest=requests.Session(); guest.trust_env=False
        assert 'page=login' in guest.get(base+'/index.php?page=ai-roadmap',timeout=20).url
        # Shared daily limit remains enforced across scenario and general modes.
        for i in range(7):
            r=selected('teacher-group')
            r,_=submit(r,'teacher-group','Lượt thử '+str(i))
        assert db.execute("SELECT COUNT(*) FROM ai_chat_messages WHERE role='user'").fetchone()[0] == 10
        r=selected('teacher-group')
        r,_=submit(r,'teacher-group','Lượt vượt hạn mức')
        assert db.execute("SELECT COUNT(*) FROM ai_chat_messages WHERE role='user'").fetchone()[0] == 10
        assert 'Bạn đã dùng 10 lượt' in r.text
        print('Career conversation HTTP checks passed: routes, fallback, consent, scoped history, duplicate protection, XSS, authentication, CSRF and shared quota.')
    finally:
        proc.terminate(); proc.wait(timeout=10)
        log.seek(0)
        if proc.returncode not in (0, -15): print(log.read()[-3000:])
        log.close(); db.close()
