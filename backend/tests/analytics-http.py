import urllib.request,urllib.error,json,http.cookiejar,secrets
base='http://127.0.0.1:8036'
def request(data=None,origin=base):
 headers={'Content-Type':'application/json','Origin':origin,'User-Agent':'ExplorandoLugaresIntegrationTest'}
 req=urllib.request.Request(base+'/api/medicion.php',data=None if data is None else json.dumps(data).encode(),headers=headers)
 try:
  with opener.open(req) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
assert request()[1]['enabled']
event={'event':'page_view','event_id':secrets.token_hex(16),'path':'/','consent':True,'policy_version':'measurement-v1'}
assert request(event,'https://untrusted.example')[0]==403 and not list(jar)
assert request({**event,'consent':False})[0]==403 and not list(jar)
assert request(event)[1]['accepted']
assert set(c.name for c in jar)=={'el_stats_visitor','el_stats_session'}
assert request(event)[1]['accepted'] is False
assert request({'action':'revoke'})[1]['revoked'] and not list(jar)
print('HTTP: origin, consent, signed cookies, replay, withdrawal OK')
