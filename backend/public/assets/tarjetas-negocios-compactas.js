/* Category chips only; structure, labels and contact icons arrive in the HTML. */
(()=>{'use strict';document.addEventListener('click',event=>{const chip=event.target.closest('[data-filter-category]');if(!chip)return;const root=chip.closest('#catalogo-local');const tab=root?.querySelector('.directory-tabs [data-category="'+CSS.escape(chip.dataset.filterCategory)+'"]');if(tab)tab.click();});})();
