"""Real HTTP ZIP upload, reimport, three languages, notification and owner publication."""
from pathlib import Path
import io, json, os, socket, struct, subprocess, tempfile, time, urllib.request, zipfile, zlib

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='el-editorial-roundtrip-') as work:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = dict(os.environ, EL_EDITORIAL_TEST='1', EL_EDITORIAL_TEST_ROOT=work)
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(ROOT/'backend/tests/editorial-roundtrip.fixture.php')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError: time.sleep(.05)
        def chunk(kind, value):
            return struct.pack('>I', len(value))+kind+value+struct.pack('>I', zlib.crc32(kind+value)&0xffffffff)
        png = b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR', struct.pack('>IIBBBBB', 640, 480, 8, 2, 0, 0, 0))+chunk(b'IDAT', zlib.compress((b'\0'+b'\x40\x70\x90'*640)*480))+chunk(b'IEND', b'')
        provenance = dict(creation_route='independent_composition',tool_model='synthetic-test',created_on='2026-10-24',responsible='fixture',prompt='Synthetic test pattern',factual_basis=['Synthetic fixture'],reference_assets=['Synthetic pattern'],reviewed_by='fixture',review_note='Test only')
        def image(name):
            return dict(file='images/'+name+'.png',alt={lang:'Patrón de prueba' for lang in ['es','en','pt-BR']},provenance=provenance)
        manifest = dict(schema='explorandolugares-editorial-result-v1',translations={lang:dict(display_name='Fixture',summary='á'*(1200-len(lang))+lang,description='Descripción '+lang) for lang in ['es','en','pt-BR']},images=dict(cover=image('cover')),products=[dict(translations={lang:dict(name=f'Producto {i}',description='Breve '+lang) for lang in ['es','en','pt-BR']},image=image('product-'+str(i))) for i in range(5)])
        buffer=io.BytesIO()
        with zipfile.ZipFile(buffer,'w',zipfile.ZIP_DEFLATED) as archive:
            archive.writestr('editorial.json',json.dumps(manifest))
            for name in ['cover',*[f'product-{i}' for i in range(5)]]: archive.writestr('images/'+name+'.png',png)
        boundary='el-editorial-roundtrip'
        body=(f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="editorial.zip"\r\nContent-Type: application/zip\r\n\r\n'.encode()+buffer.getvalue()+f'\r\n--{boundary}--\r\n'.encode())
        request=urllib.request.Request(f'http://127.0.0.1:{port}/',body,{'Content-Type':'multipart/form-data; boundary='+boundary})
        try:
            with urllib.request.urlopen(request) as response:
                raw=response.read().decode()
                try: result=json.loads(raw)
                except ValueError: raise AssertionError(raw)
        except urllib.error.HTTPError as error: raise AssertionError(error.read().decode()) from error
        assert result==dict(ok=True,products=5,mail='mail_simulated',status='published'),result
        print('OK: real ZIP upload and reimport, five products, ES/EN/PT-BR, simulated email and publication by owner.')
    finally:
        process.terminate();process.wait(timeout=10)
