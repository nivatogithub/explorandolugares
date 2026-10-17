#!/usr/bin/env python3
"""Compare local HTTP runtime with the sanitized GoDaddy observation. No production access."""
import json,re,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
expected=json.loads((ROOT/'preprod/infra/production-runtime.json').read_text())
with urllib.request.urlopen('http://127.0.0.1:8088/__preprod-runtime.php',timeout=30) as response:actual=json.load(response)
checks={}
checks['php_version']=actual['php']['version']==expected['php']['version']
checks['architecture']=actual['architecture']==expected['architecture']
checks['web_server_family']=actual['web_server'].split('/')[0]==expected['web_server'].split('/')[0]
checks['os_family']=actual['os_family']==expected['os_family']
checks['database_engine_version']=re.match(r'\d+\.\d+\.\d+',actual['database']['runtime']['version']).group()==re.match(r'\d+\.\d+\.\d+',expected['database']['runtime']['version']).group() and 'MariaDB' in actual['database']['runtime']['version']
for key,value in expected['php']['ini'].items():
 checks['php_ini.'+key]=str(actual['php']['ini'].get(key))==str(value)
for key in ['sql_mode','server_charset','server_collation','connection_charset','connection_collation','time_zone','system_time_zone','lower_case_table_names']:
 checks['database.'+key]=str(actual['database']['runtime'].get(key))==str(expected['database']['runtime'][key])
checks['schema_defaults']=actual['database']['schema']==expected['database']['schema']
required=['pdo_mysql','mysqli','mbstring','gd','zip','intl','curl','fileinfo','openssl','session','dom','xml','exif']
checks['application_extensions']=all(ext in actual['php']['extensions'] for ext in required)
report={'checks':checks,'failures':[k for k,v in checks.items() if not v],'intentional_differences':{'php_sapi':{'production':expected['php']['sapi'],'local':actual['php']['sapi']},'extensions_not_replicated':sorted(set(expected['php']['extensions'])-set(actual['php']['extensions'])),'extra_local_extensions':sorted(set(actual['php']['extensions'])-set(expected['php']['extensions']))},'actual':actual}
(ROOT/'.local/preprod/parity-report.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k!='actual'},indent=2))
raise SystemExit(bool(report['failures']))
