"""PQRS HTTP/BD + disputa de representación; solo fixtures de la ejecución.
Importar desde el runner local, que establece las guardas del entorno.
Este módulo no se ejecuta al importarlo ni conecta a producción.
"""
import json
import re


def run(env):
    case, check, query = env['case'], env['check'], env['query']
    Client, csrf, root, run_id = env['Client'], env['csrf'], env['ROOT'], env['RUN']
    # Distinct exact emails, never wildcard cleanup or shared persistent accounts.
    emails = {kind: run_id+'-pqrs-'+kind+'@pruebas.invalid'
              for kind in ('suggestion', 'petition', 'complaint', 'claim')}
    client, admin, ordinary = Client(), Client(), Client()
    references = {}

    def post(path, fields):
        result = client.request(path, fields)
        if result[0] == 400 and 'Has enviado varias solicitudes; espera' in result[1]:
            raise env['Blocked']('Límite antiabuso PQRS vigente; repetir tras la ventana, sin borrar contadores.')
        return result

    def form():
        code, body, _ = client.get('/contacto.php')
        check(code == 200 and '<form' in body, 'Contacto anónimo no abre formulario')
        for kind in emails:
            check('value="'+kind+'"' in body, 'Falta tipo de solicitud '+kind)
        check('/negocios-locales/interes/' in body and '/negocios-locales/impulsor/' in body,
              'Contacto no diferencia negocio y promotor de PQRS')
        return body

    def submit(kind):
        fields = {'csrf': csrf(form()), 'request_type': kind,
                  'name': run_id+' Solicitud sintética', 'email': emails[kind],
                  'message': 'PRUEBA AUTOMATIZADA LOCAL: '+run_id+' '+kind,
                  'consent': '1', 'website': ''}
        code, body, _ = post('/contacto.php', fields)
        check(code == 200, 'No registra solicitud anónima '+kind+'; HTTP '+str(code))
        rows = query('SELECT * FROM public_requests WHERE email=?', [emails[kind]])
        check(len(rows) == 1, 'La solicitud no persistió exactamente una vez')
        row = rows[0]
        check(row['request_type'] == kind and row['status'] == 'pending', 'Tipo/estado incorrecto')
        check(row['name'] == fields['name'] and row['message'] == fields['message'], 'Datos perdidos')
        check(row['consent_version'] == 'public-request-v1', 'No guarda consentimiento de atención')
        check(re.fullmatch(r'[A-F0-9]{24}', row['reference']) is not None, 'Referencia no válida')
        check(row['reference'] in body and 'Solicitud registrada' in body, 'No entrega referencia')
        references[kind] = row
        # Confirmation is session scoped and disappears; public GET does not expose email/messages.
        _, second, _ = client.get('/contacto.php')
        check(row['reference'] not in second, 'Referencia permanece expuesta fuera de confirmación')
        _, other, _ = Client().get('/contacto.php?reference='+row['reference'])
        check(row['message'] not in other and row['email'] not in other,
              'Otro visitante puede leer contenido privado por referencia')

    def csrf_denied():
        form()
        code, _, _ = post('/contacto.php', {
            'csrf': 'token-invalido', 'request_type': 'suggestion', 'name': run_id,
            'email': emails['suggestion'], 'message': 'No debe persistir sin CSRF válido.', 'consent': '1'})
        check(code in (400, 403), 'Acepta formulario sin CSRF válido')
        check(not query('SELECT id FROM public_requests WHERE email=?', [emails['suggestion']]),
              'CSRF inválido persistió datos')

    def consent_denied():
        # This is the fifth throttled attempt; invalid CSRF never consumes form throttle.
        code, _, _ = post('/contacto.php', {
            'csrf': csrf(form()), 'request_type': 'claim', 'name': run_id,
            'email': emails['claim'], 'message': 'No debe registrarse sin consentimiento.'})
        check(code == 400, 'No rechaza falta de consentimiento')
        check(len(query('SELECT id FROM public_requests WHERE email=?', [emails['claim']])) == 1,
              'Guardó otra solicitud sin consentimiento')

    def login(target, key):
        info = json.loads((root/'.local/preprod/test-accounts.json').read_text())['profiles'][key]
        _, page, _ = target.get('/negocios/acceso/')
        code, _, _ = target.request('/negocios/acceso/', {
            'csrf': csrf(page), 'email': info['email'], 'password': info['password']})
        check(code == 200 and target.state().get('authenticated'), 'No inicia sesión '+key)

    def administrative_access():
        check(len(references) == 4, 'Faltan solicitudes previas para verificar acceso')
        login(admin, 'administrador')
        check(admin.state().get('administrator'), 'Falta rol administrador')
        code, body, _ = admin.get('/admin/solicitudes-publicas.php')
        check(code == 200 and 'Solicitudes de usuarios' in body, 'No abre atención administrativa')
        for row in references.values():
            check(row['reference'] in body and row['message'] in body, 'Solicitud ausente en panel')
        row = references['petition']
        note = 'Prueba sintética local: atención registrada, sin envío de correos.'
        code, _, _ = admin.request('/admin/solicitudes-publicas.php', {
            'csrf': csrf(body), 'id': row['id'], 'status': 'in_review', 'response_note': note})
        check(code == 200, 'Administrador no puede registrar atención')
        updated = query('SELECT status,response_note,reviewed_at FROM public_requests WHERE id=? AND email=?',
                        [row['id'], emails['petition']])[0]
        check(updated['status'] == 'in_review' and updated['response_note'] == note and updated['reviewed_at'],
              'Revisión administrativa no persistió')

    def private_access_denied():
        check(references, 'Faltan solicitudes para comprobar privacidad')
        login(ordinary, 'viajero')
        check(not ordinary.state().get('administrator'), 'Fixture viajero tiene permisos administrativos')
        for target, label in ((Client(), 'anónimo'), (ordinary, 'viajero')):
            code, body, _ = target.get('/admin/solicitudes-publicas.php')
            check(code == 403, 'Expone solicitudes privadas a '+label)
            for row in references.values():
                check(row['email'] not in body and row['message'] not in body, 'Filtración a '+label)
            before = query('SELECT status,response_note FROM public_requests WHERE email=?', [emails['petition']])
            code, _, _ = target.request('/admin/solicitudes-publicas.php', {
                'csrf': target.state().get('csrf', ''), 'id': references['petition']['id'],
                'status': 'resolved', 'response_note': 'Cambio no autorizado'})
            check(code == 403, 'Acepta revisión de '+label)
            check(query('SELECT status,response_note FROM public_requests WHERE email=?', [emails['petition']]) == before,
                  'Usuario sin permiso alteró solicitud')

    try:
        case('CT-03-00', 'Contacto anónimo distingue PQRS, negocio y promotor', form)
        case('CT-03-CSRF', 'PQRS rechaza CSRF inválido sin guardar', csrf_denied)
        for kind in emails:
            case('CT-03-'+kind, 'PQRS anónima: '+kind+' persiste y entrega referencia', lambda kind=kind: submit(kind))
        case('CT-03-consent', 'PQRS requiere consentimiento de atención', consent_denied)
        case('CT-03-admin', 'Administrador consulta y registra atención sin enviar correo', administrative_access)
        case('CT-03-privacy', 'Anónimo y viajero no pueden consultar ni editar PQRS', private_access_denied)
    finally:
        def cleanup():
            for email in emails.values():
                query('DELETE FROM public_requests WHERE email=?', [email])
                check(not query('SELECT id FROM public_requests WHERE email=?', [email]), 'Limpieza PQRS incompleta')
        case('CT-03-limpieza', 'Retirar únicamente cuatro emails PQRS de esta ejecución', cleanup)


def disputed_claim(env):
    """Replacement callable for NG-05, after NG-00; uses existing two-account fixture.
    The main runner owns fixture/evidence/account cleanup. No additional identities.
    """
    query, check, fixtures = env['query'], env['check'], env['fixtures']
    bid, aid = fixtures[1], env['account']()['id']
    before = query('SELECT account_id,role,status FROM business_account_memberships WHERE business_id=? ORDER BY account_id', [bid])
    check(any(row['account_id'] != aid and row['status'] == 'active' for row in before), 'Falta propietario distinto del reclamante')
    business = query('SELECT claim_status,information_status,publication_status FROM businesses WHERE id=?', [bid])
    code, page, _ = env['application']('existing_business', bid)
    check(code == 200, 'No guarda reclamación controvertida')
    rows = query('SELECT application_type,status,business_details_json FROM business_account_applications WHERE account_id=? AND business_id=?', [aid, bid])
    check(len(rows) == 1, 'No persistió exactamente una reclamación')
    check(rows[0]['application_type'] == 'claim_existing' and rows[0]['status'] == 'in_review', 'No distingue revisión controvertida')
    check(json.loads(rows[0]['business_details_json'])['claim_review_kind'] == 'disputed_existing', 'Falta marcador estructurado')
    check('responsable actual' in page, 'Solicitante no recibe explicación de revisión')
    check(query('SELECT account_id,role,status FROM business_account_memberships WHERE business_id=? ORDER BY account_id', [bid]) == before, 'Se modificó propiedad o permisos')
    check(query('SELECT claim_status,information_status,publication_status FROM businesses WHERE id=?', [bid]) == business, 'Se alteró negocio durante disputa')
    code, _, _ = env['application']('existing_business', bid)
    check(code == 400 and len(query('SELECT id FROM business_account_applications WHERE account_id=? AND business_id=?', [aid, bid])) == 1, 'Duplica reclamación abierta')
