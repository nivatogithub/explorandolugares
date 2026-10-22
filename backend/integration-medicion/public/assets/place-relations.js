/* Same-page relationships are in the HTML. Only reveal/focus behavior needs JS. */
(()=>{'use strict';
 function reveal(target){const grid=target.closest('.highlight-grid,.destination-experience-list');const button=grid?.nextElementSibling;if(button?.matches('.row-load-more'))for(let n=0;target.hidden&&!button.hidden&&n<grid.children.length;n++)button.click();}
 document.addEventListener('click',event=>{const link=event.target.closest('[data-place-relation]');if(!link)return;const target=document.getElementById(link.hash.slice(1));if(target){reveal(target);target.focus({preventScroll:true});}});
 function fromHash(){let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return;}const target=document.getElementById(id);if(target?.matches('[data-place-slug],[data-experience-slug]')){reveal(target);target.scrollIntoView({block:'start'});}}
 window.addEventListener('load',fromHash,{once:true});window.addEventListener('hashchange',fromHash);
})();
