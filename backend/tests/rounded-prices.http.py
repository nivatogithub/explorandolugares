#!/usr/bin/env python3
"""Read-only HTTP acceptance against a reserved dev; never creates another server."""
import argparse,json,re,urllib.request
p=argparse.ArgumentParser();p.add_argument('--base',choices=['http://127.0.0.1:8086','http://127.0.0.1:8087','http://127.0.0.1:8088'],required=True);base=p.parse_args().base
def get(path):
    with urllib.request.urlopen(base+path,timeout=20) as r:
        assert r.status==200
        return r.read().decode()
catalog=json.loads(get('/api/subscription-prices.php'));plans={row['code']:row for row in catalog['plans']}
expected={'featured':[25000,67500,127500,240000],'page':[40000,108000,204000,384000],'page_premium':[60000,162000,306000,576000]}
periods=['monthly','quarterly','semiannual','annual']
for code,prices in expected.items():
    for period,amount in zip(periods,prices):assert float(plans[code]['prices'][period]['quoted_amount'])==amount
for period,amount,discount in zip(periods,[20000,54000,102000,192000],[0,10,15,20]):
    assert float(catalog['operator'][period]['amount'])==amount
    assert catalog['periods'][period]['discount_percentage']==discount
for prefix,separator in [('', '.'),('en/',','),('pt-br/','.')]:
    for suffix in ['','index.html']:
        html=get('/'+prefix+'negocios-locales/suscripciones/'+suffix)
        assert 'subscription-current-prices' in html,'The HTML must include live prices before JavaScript'
        for prices in expected.values():
            for amount in prices:assert '$'+format(amount,',').replace(',',separator)+' COP' in html
        for percent in [10,15,20]:assert str(percent)+' %' in html
        assert not re.search(r'29[.,]900|49[.,]900|79[.,]900|119[.,]900|74[.,]750|155[.,]480',html)
    process=get('/'+prefix+'negocios-locales/proceso/')
    assert '$20'+separator+'000 COP' in process and '20 %' in process
print('OK HTTP: 16 catalogue prices, explicit 10/15/20%, PHP-rendered table and experience prices, ES/EN/PT-BR, directory/index.html parity, no stale prices.')
