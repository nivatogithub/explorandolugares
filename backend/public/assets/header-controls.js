// Refresh the shared identity URL used by older published HTML.
(()=>{const style=document.querySelector('link[href*="/assets/identidad-neutral.css"]');if(style)style.href='/assets/identidad-neutral.css?v=lino-natural-1';})();
(()=>{const header=document.querySelector('header'),old=header?.querySelector('.language-switch');if(!header||!old||header.querySelector('.header-language-bar'))return;
const items=[...old.querySelectorAll(':scope > a[hreflang],:scope > a[lang]')];if(!items.length)return;
const current=document.documentElement.lang.toLowerCase();const label=current.startsWith('en')?'Language':current.startsWith('pt')?'Idioma':'Idioma';
const bar=document.createElement('div');bar.className='header-language-bar';const nav=document.createElement('nav');nav.className='header-language-options';nav.setAttribute('aria-label',label);
const title=document.createElement('span');title.className='header-language-label';title.textContent=label;bar.append(old,nav);
const map={es:['🇨🇴','Español'],'en':['🇬🇧','English'],'pt':['🇧🇷','Português']};
items.forEach(a=>{const code=(a.getAttribute('hreflang')||a.lang||'').toLowerCase().split('-')[0],data=map[code];if(!data)return;const selected=current.split('-')[0]===code;a.replaceChildren();const flag=document.createElement('span');flag.className='header-language-flag';flag.setAttribute('aria-hidden','true');flag.textContent=data[0];const name=document.createElement('span');name.textContent=code==='pt'?'PT-BR':code.toUpperCase();a.append(flag,name);a.setAttribute('aria-label',data[1]);if(selected)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');nav.append(a);});
old.setAttribute('aria-label',current.startsWith('en')?'Your account':current.startsWith('pt')?'Sua conta':'Tu cuenta');const wrap=header.querySelector('.wrap');const toggle=wrap?.querySelector('.menu-toggle');if(toggle)toggle.before(bar);else wrap?.append(bar);
})();
