#!/usr/bin/env python3
"""Compare shared phone compatibility adapters and explicit prepared rendering without HTTP."""
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
import argparse,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$phoneValues=$i['values'];$_POST=$i['input']===null?[]:['business_phone'=>$i['input']];
if($i['explicit']){require $i['package'].'/app/BusinessPhonePresentation.php';BusinessPhonePresentation::render(BusinessPhonePresentation::context($phoneValues,$i['input']));}
else require $i['package'].'/public/business/phone-fields.php';
'''
def run(before,after,output):
    before,after=before.resolve(),after.resolve();assert all(p.is_relative_to(ROOT/'.local') for p in [before,after])
    spec=spec_from_file_location('auth_phone',ROOT/'scripts/check-account-auth-views.py');auth=module_from_spec(spec);spec.loader.exec_module(auth)
    checks=[]
    cases=[('empty',{},None),('mobile',{'phone':'+573001234567','whatsapp':'+573001234567'},None),('fixed',{'phone_fixed':'+576011234567'},None),('different-whatsapp',{'phone':'3001234567','whatsapp':'3017654321'},None),('post-overrides-stored',{'phone':'+573001234567'},{'type':'fixed','area':'602','number':'7654321','whatsapp':'3001112233','same_whatsapp':'0'}),('invalid-area',{}, {'type':'fixed','area':'invalid','number':'1234567'}),('escaped-input',{}, {'type':'mobile','number':'<script> & "safe"','whatsapp':'<value>'}),('malformed-input',{},'invalid'),('malformed-channels','invalid',None)]
    for name,values,input in cases:
        outcomes=[]
        for package,explicit in [(before,False),(after,False),(after,True)]:
            result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps({'package':str(package),'values':values,'input':input,'explicit':explicit}),text=True,capture_output=True,check=True)
            assert not result.stderr.strip(),(name,result.stderr);parser=auth.AuthMarkup(package/'public','/negocios/registro/');parser.feed(result.stdout);outcomes.append(parser.events)
        assert outcomes[0]==outcomes[1]==outcomes[2],(name,'phone presentation changed');checks.append(name)
    output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'database_accessed':False,'checks':checks,'legacy_adapter_and_prepared_component_identical':True},indent=2)+'\n')
    print(f'OK: {len(checks)} phone comparisons preserve stored values, submitted overrides, area fallback and escaping across both consumers.')
if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for name in ['before','after','output']:p.add_argument('--'+name,type=Path,required=True)
    run(**vars(p.parse_args()))
