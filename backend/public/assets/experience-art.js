/* Reusable editorial activity illustrations. No photographs or third-party assets. */
(() => {
  'use strict';
  const script = document.currentScript;
  if (!script || script.dataset.destination !== 'cartagena') return;
  const locale = document.documentElement.lang;
  const label = locale.startsWith('en') ? 'Activity illustration' : locale.startsWith('pt') ? 'Ilustração da atividade' : 'Ilustración de actividad';
  const ink = '#172b42', accent = '#c94720', sand = '#f2c77e', sea = '#418d93';
  const common = `<rect width="600" height="360" fill="#fff0dc"/><circle cx="460" cy="83" r="43" fill="${sand}"/><path d="M35 94h64m-40 12h82m344 27h59" stroke="${ink}" stroke-opacity=".15" stroke-width="3" stroke-linecap="round"/><path d="M0 316Q140 278 290 313T600 305V360H0Z" fill="${sand}" opacity=".5"/>`;
  const waves = `<path d="M0 250Q70 225 150 250T300 250T450 250T600 250V360H0Z" fill="${sea}"/><path d="M30 291q28-15 56 0t56 0m45 30q28-15 56 0t56 0m81-32q28-15 56 0t56 0" fill="none" stroke="#fff8ed" stroke-width="4" stroke-linecap="round" opacity=".7"/>`;
  const fish = `<path d="M100 197q30-25 57 0-27 25-57 0l-17 15v-30Z" fill="${sand}"/><circle cx="143" cy="193" r="3" fill="${ink}"/><path d="M450 235q22-18 42 0-20 18-42 0l-13 10v-20Z" fill="#fff0dc"/>`;
  const arch = `<path d="M93 281V132h81v149m-81-149 40-28 41 28" fill="#df9b70" stroke="${ink}" stroke-width="3"/><path d="M112 281v-57a21 21 0 0 1 42 0v57" fill="#fff0dc"/><path d="M226 283V96h195v187" fill="#fff8ed" stroke="${ink}" stroke-width="3"/><path d="M215 96h217l-109-40Z" fill="${accent}" stroke="${ink}" stroke-width="3"/><path d="M250 283v-76a24 24 0 0 1 48 0v76m30 0v-76a24 24 0 0 1 48 0v76" fill="${sand}" stroke="${ink}" stroke-width="3"/>`;
  const scenes = {
    sailing: `${waves}<path d="M185 254h245l-35 29H216Z" fill="${ink}"/><path d="M302 74v181" stroke="${ink}" stroke-width="6"/><path d="m291 90-98 143h98Z" fill="#fffdf8" stroke="${ink}" stroke-width="3"/><path d="m315 119 88 114h-88Z" fill="${accent}"/><path d="M225 246h147" stroke="${sand}" stroke-width="5" stroke-linecap="round"/><path d="m479 165 11-7 11 7m-58-24 8-5 8 5" fill="none" stroke="${ink}" stroke-width="3"/>`,
    diving: `${waves}<path d="M0 170q140-40 300 0t300 0v190H0Z" fill="${sea}"/>${fish}<g transform="rotate(-16 302 205)" stroke="${ink}" stroke-width="4" stroke-linejoin="round"><rect x="242" y="172" width="75" height="37" rx="18" fill="${accent}"/><rect x="262" y="158" width="48" height="19" rx="9" fill="${sand}"/><circle cx="339" cy="192" r="23" fill="#df9b70"/><path d="M337 177h30v21h-30Z" fill="#fff8ed"/><path d="m249 188-49 31-40-11m100-8-47 43-40 4m143-40 30 25 36-10" fill="none" stroke-width="13" stroke-linecap="round"/><path d="m160 203-26-13-3 19 31 11m9 18-29 7 5 15 31-10" fill="${sand}"/></g><circle cx="385" cy="140" r="10" fill="none" stroke="#fff8ed" stroke-width="3"/><circle cx="402" cy="111" r="6" fill="none" stroke="#fff8ed" stroke-width="3"/>`,
    snorkel: `${waves}<path d="M40 206q125-17 240 0t280 0" fill="none" stroke="${sea}" stroke-width="4"/>${fish}<path d="M220 218h107" stroke="${accent}" stroke-width="24" stroke-linecap="round"/><circle cx="347" cy="216" r="18" fill="#df9b70" stroke="${ink}" stroke-width="3"/><path d="M349 201h24v17h-24m13-18v-35h14" fill="#fff8ed" stroke="${ink}" stroke-width="5" stroke-linejoin="round"/><path d="m216 220-44 12-31-16m80 8-48 30-35-3" fill="none" stroke="${ink}" stroke-width="11" stroke-linecap="round"/><path d="m139 210-28-10 3 21 25 1m-2 23-29 1 7 17 23-7" fill="${sand}"/>`,
    walking: `${arch}<path d="M0 327h600M62 305h143m223 0h110" stroke="${ink}" opacity=".2" stroke-width="3"/><path d="m238 360 69-77h59l107 77" fill="${sand}"/><g stroke="${ink}" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"><circle cx="194" cy="195" r="15" fill="#df9b70"/><path d="m189 218-6 49 30 22 17 32m-48-54-23 48m26-87-25 24m28-26 27 23" fill="none"/><path d="m180 218 29 1-5 43-28-3Z" fill="${accent}" stroke-width="3"/><path d="m174 219-13 9-4 28 17 2" fill="${sea}" stroke-width="3"/></g>`,
    museum: `${arch}<rect x="79" y="201" width="110" height="92" rx="5" fill="${sea}" stroke="${ink}" stroke-width="3"/><path d="m92 273 31-38 18 20 22-32 14 50Z" fill="${sand}"/><circle cx="108" cy="221" r="8" fill="#fff8ed"/><path d="M478 173v94m-23 0h46m-38-88 15-41 15 41Z" fill="${accent}" stroke="${ink}" stroke-width="3"/><path d="M449 284h63v15h-63Z" fill="${sand}"/>`,
    bus: `<path d="M39 255V154h44v101m371 0V128h76v127" fill="${sand}"/><path d="M58 154v-35m411 9v-32h41v32" fill="none" stroke="${ink}" stroke-width="3"/><rect x="126" y="132" width="318" height="132" rx="24" fill="${accent}" stroke="${ink}" stroke-width="4"/><path d="M144 161h204v48H144Zm225 0h54v48h-54Z" fill="#fff8ed" stroke="${ink}" stroke-width="3"/><path d="M205 161v48m70-48v48m73-48v48" stroke="${ink}" stroke-width="3"/><path d="M146 228h278" stroke="${sand}" stroke-width="5"/><circle cx="192" cy="266" r="28" fill="${ink}"/><circle cx="192" cy="266" r="12" fill="#fff8ed"/><circle cx="376" cy="266" r="28" fill="${ink}"/><circle cx="376" cy="266" r="12" fill="#fff8ed"/><path d="M95 308h382" stroke="${ink}" stroke-width="3" stroke-linecap="round"/>`,
    mangrove: `${waves}<g fill="#718b61"><circle cx="69" cy="132" r="52"/><circle cx="114" cy="107" r="49"/><circle cx="520" cy="125" r="54"/><circle cx="565" cy="155" r="44"/></g><g stroke="${ink}" fill="none" stroke-width="5" stroke-linecap="round"><path d="M92 143v105l-36 34m36-34 37 34m-37-34-5 49m5-98-31-40m31 23 31-31m402 8v97l-35 36m35-36 36 31m-36-31 1 44m-1-102 25-34"/></g><path d="M185 264q110 30 230-7l-34 36H226Z" fill="${accent}" stroke="${ink}" stroke-width="3"/><circle cx="296" cy="198" r="15" fill="#df9b70" stroke="${ink}" stroke-width="3"/><path d="m285 218-9 43h43l-10-43" fill="${sand}" stroke="${ink}" stroke-width="3"/><path d="m306 227 54 62m-12-15 23 33" stroke="${ink}" stroke-width="7" stroke-linecap="round"/>`
  };
  const kinds = {'caminhada-patrimonial':'walking','visita-muhca':'museum','bus-panoramico':'bus','buceo-rosario':'diving','snorkel-rosario':'snorkel','catamaran-cartagena':'sailing','pesca-boquilla':'mangrove'};
  for (const card of document.querySelectorAll('.destination-experience-card[data-experience-slug]')) {
    const kind = kinds[card.dataset.experienceSlug];
    if (!kind || card.querySelector('img, .experience-art')) continue;
    const figure = document.createElement('figure');
    figure.className = 'experience-art';
    figure.dataset.kind = kind;
    // Only constant local SVG geometry is parsed; no user text enters SVG markup.
    figure.innerHTML = `<svg viewBox="0 0 600 360" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">${common}${scenes[kind]}</svg>`;
    const caption = document.createElement('figcaption');
    caption.textContent = label;
    figure.append(caption);
    card.prepend(figure);
  }
})();
