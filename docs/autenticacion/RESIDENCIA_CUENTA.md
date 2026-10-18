# Residencia de la persona

Migración038: `account_residences`, relación uno a uno con accounts y eliminación en cascada. Separada de destino, negocio, IP, nacionalidad y permiso comercial. Declaración privada, no prueba de presencia física. Las cuentas existentes no reciben datos inferidos.

Registro público nuevo: país, división administrativa y localidad obligatorios; captura antes de crear cuenta y persistencia dentro de la transacción del alta. Mi cuenta → Personal permite completar/corregir. El servidor toma siempre el ID de sesión, verifica CSRF y vuelve a validar país, estado/departamento y municipio. Colombia rechaza municipios de otro departamento.

Colombia: selectores de país, departamento y municipio a partir del catálogo territorial existente de cobertura. Estados Unidos: país, estado (incluido DC), ciudad escrita. Brasil: país, estado/DF, municipio escrito. Otros países: país del catálogo ISO y región/localidad escritas. No afirmar validación de localidades extranjeras: aún falta incorporar catálogos oficiales. Los nombres visibles de países se localizan con Intl.DisplayNames; sin JS se conservan nombres de catálogo.

Fuentes: códigos de país del archivo IANA tzdb iso3166.tab del equipo (dominio público, edición del 1 julio2025); municipios/departamentos CO reutilizados del catálogo de cobertura; US estados/DC y BR estados/DF incluidos explícitamente. Referencias de estructura: https://www.census.gov/programs-surveys/popest/about/glossary/geo-terms.html y https://servicodados.ibge.gov.br/api/docs/localidades. Snapshot versionado en app/content/account-geography.json y copia pública sin información de usuarios. No hay llamadas externas durante el registro.

Pruebas: backend/tests/account-residence.php, account-tabs.integration.py (guardar/corregir, no aceptar municipio de otro departamento), admin-pages.integration.py y campos actualizados de la suite local. No se habilita ninguna publicación ni mensaje comercial por guardar residencia.
