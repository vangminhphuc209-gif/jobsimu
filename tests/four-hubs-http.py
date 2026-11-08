"""Run only against a disposable seeded fixture with the documented test accounts."""
import re, requests, os
BASE=os.environ.get('HUB_TEST_URL','http://127.0.0.1:8091')
assert BASE.startswith(('http://127.0.0.1:', 'http://localhost:')), 'Test fixture must be local'
s=requests.Session();s.trust_env=False
def get(page):
 r=s.get(BASE+'/index.php',params={'page':page});assert r.status_code==200,(page,r.status_code);assert not re.search(r'(Fatal error|Warning:|Parse error)',r.text),page;return r

def csrf(r):return re.search(r'name="csrf" value="([^"]+)"',r.text)[1]
for page in ['home','knowledge-hub','careers','simulations','login','register']:get(page)
r=get('login');r=s.post(BASE+'/index.php?page=login',data={'csrf':csrf(r),'email':'student@example.test','password':'TestPassword123'});assert 'Xin chào' in r.text
for page in ['assessment-hub','test-mbti','test-eq','assessment-report','dashboard','assessment','simulation-3d','ai-roadmap']:get(page)
for typ,n in [('mbti',12),('eq',10)]:
 r=get('test-'+typ);keys=list(dict.fromkeys(re.findall(r'name="(answers\[[^]]+\])"',r.text)));assert len(keys)==n
 r=s.post(BASE+'/index.php?page=test-'+typ,data={'csrf':csrf(r),**{key:'3' for key in keys}});assert 'Báo cáo đánh giá tổng quan' in r.text
 r=s.post(BASE+'/index.php?page=test-'+typ,data={key:'3' for key in keys});assert r.status_code==419
r=get('ai-roadmap');nonce=re.search(r'name="chat_nonce" value="([^"]+)"',r.text)[1]
r=s.post(BASE+'/index.php?page=ai-chat',data={'csrf':csrf(r),'chat_nonce':nonce,'message':'MBTI có nghĩa gì?'});assert 'Gợi ý dự phòng' in r.text
r=s.get(BASE+'/admin.php');assert r.status_code==403
s=requests.Session();s.trust_env=False;r=get('login');r=s.post(BASE+'/index.php?page=login',data={'csrf':csrf(r),'email':'admin@example.test','password':'TestPassword123'})
for kind in ['articles','portals']:
 r=s.get(BASE+'/admin.php?section=knowledge-'+kind);assert r.status_code==200;assert 'knowledge-editor' in r.text
r=s.get(BASE+'/admin.php?section=knowledge-articles');r=s.post(BASE+'/admin.php?section=knowledge-articles-save',data={'csrf':csrf(r),'id':'0','title':'Test article','slug':'test-article','category':'guide','summary':'Test summary','content':'<script>alert(1)</script>','status':'published'})
assert r.status_code==200
r=s.get(BASE+'/index.php?page=knowledge-hub&article=test-article');assert '&lt;script&gt;' in r.text and '<script>alert(1)</script>' not in r.text
print('HTTP checks passed: public routes, login, legacy routes, quizzes, CSRF, chat fallback, admin authorization and escaped article output.')
