(() => {
  const sidebar=document.querySelector('.admin-sidebar-with-system');
  const toggle=sidebar?.querySelector('.admin-mobile-nav-toggle');
  if(!sidebar||!toggle)return;
  const mobile=window.matchMedia('(max-width:850px)');
  function setCollapsed(collapsed){
    sidebar.dataset.navCollapsed=String(collapsed);
    toggle.setAttribute('aria-expanded',String(!collapsed));
    toggle.textContent=collapsed?'Mostrar secciones del panel':'Ocultar secciones del panel';
  }
  toggle.hidden=false;
  toggle.addEventListener('click',()=>setCollapsed(sidebar.dataset.navCollapsed!=='true'));
  mobile.addEventListener('change',()=>setCollapsed(mobile.matches));
  setCollapsed(mobile.matches);
})();
