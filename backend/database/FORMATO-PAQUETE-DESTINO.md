# Paquete ZIP para crear un destino

La administración importa un solo archivo `.zip` para preparar un destino completo. El ZIP se valida y se usa para rellenar el formulario; importar no guarda en la base de datos ni publica. Después de revisar textos, traducciones e imágenes, pulsa **Guardar municipio como borrador**. La publicación requiere una acción manual posterior.

## Estructura

```text
paquete-destino.zip
├── paquete-destino.json
└── images/
    ├── nombre-del-destino-portada.webp
    ├── atractivo-1.webp
    └── atractivo-2.webp
```

El manifiesto usa `schema_version: 2`. `destination.destination_type` acepta `municipality`, `city` u `other`, y `destination.country_code` usa el código ISO de dos letras. `hero_image.file` referencia la portada; cada `places[].image.file` referencia una imagen. Un elemento puede incluir `type: "place"` (valor predeterminado) o `type: "experience"`. Las experiencias llevan `external_url` y `external_label` por idioma para enlazar a la persona directamente con un operador; deben ser enlaces HTTPS. El portal no confirma disponibilidad ni gestiona reservas. Las rutas de las imágenes deben empezar por `images/` y usar nombres de archivo seguros. Los nombres deben coincidir exactamente entre el JSON y el ZIP. No incluyas carpetas ni archivos adicionales. El ejemplo completo está en `backend/public/plantillas/paquete-destino-v2.zip`; el manifiesto legible está en `ensayos/v1/plantillas/paquete-destino-v2.json`.

El paquete debe llevar traducciones completas en español (`es`), inglés (`en`) y portugués de Brasil (`pt-BR`), historia, razones para visitar, atractivos y datos internos opcionales de posicionamiento, competencia, posibles aliados, prospectos y estrategia comercial. Mantén los atractivos en borrador. No inventes negocios, alianzas, fuentes, licencias ni hechos locales. Investigación, prospectos y estrategias permanecen en administración y no se publican.

Se aceptan imágenes JPG, PNG y WebP, de 240 × 160 hasta 10000 × 10000 px y hasta 5 MB cada una. El ZIP admite hasta 64 MB y hasta 60 MB de contenido descomprimido, con portada y hasta 40 imágenes de atractivos. Optimiza imágenes grandes antes de empaquetarlas. No se admiten SVG, enlaces simbólicos, rutas fuera de `images/`, archivos no referenciados ni imágenes ausentes. Las imágenes externas requieren crédito, origen y licencia verificables; conserva esa información en los campos del JSON.

## Importación y seguridad

1. En **Administración → Destinos**, importa el ZIP.
2. El servidor valida el manifiesto, nombres y contenido real de las imágenes. Las copias temporales solo quedan disponibles para tu sesión autenticada durante 30 minutos.
3. El navegador carga textos e imágenes en el formulario para que los revises. Nada se guarda todavía.
4. Al guardar, PHP valida los campos de nuevo, copia las imágenes a `assets/destinos/{id}/`, registra su procedencia y crea el destino en estado borrador.
5. Abre la vista previa privada. Solo publica cuando el contenido y las imágenes estén aprobados.

La importación JSON v1 sigue disponible como compatibilidad con paquetes anteriores, pero requiere adjuntar imágenes manualmente. No subas credenciales, archivos de configuración ni datos privados en el ZIP.
