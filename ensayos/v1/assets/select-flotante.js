(() => {
  const openMenus = new Set();
  const closeAll = except => {
    for (const control of [...openMenus]) if (control !== except) control.close();
  };

  function enhance(select) {
    if (select.dataset.floatingSelect === 'ready' || select.multiple || select.size > 1) return;
    select.dataset.floatingSelect = 'ready';
    const wrapper = document.createElement('span');
    wrapper.className = 'floating-select';
    select.before(wrapper); wrapper.append(select);
    select.classList.add('floating-select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.className = 'floating-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const label = document.createElement('span'); label.className = 'floating-select-label';
    const divider = document.createElement('span'); divider.className = 'floating-select-divider';
    const arrow = document.createElement('span'); arrow.className = 'floating-select-arrow'; arrow.setAttribute('aria-hidden', 'true'); arrow.textContent = '⌄';
    trigger.append(label, divider, arrow);
    const menu = document.createElement('div');
    menu.className = 'floating-select-menu'; menu.setAttribute('role', 'listbox'); menu.hidden = true;
    wrapper.append(trigger, menu);

    const control = {
      wrapper,
      close() {
        menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); wrapper.classList.remove('is-open'); openMenus.delete(control);
      },
      open() {
        if (select.disabled) return;
        closeAll(control); refresh(); menu.hidden = false; trigger.setAttribute('aria-expanded', 'true'); wrapper.classList.add('is-open'); openMenus.add(control);
      },
    };

    function change(value) {
      if (select.value === value) return;
      select.value = value;
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function refresh() {
      const selected = select.options[select.selectedIndex] || select.options[0];
      label.textContent = selected ? selected.textContent : '';
      trigger.disabled = select.disabled;
      trigger.setAttribute('aria-label', select.getAttribute('aria-label') || selected?.textContent || 'Seleccionar');
      menu.replaceChildren();
      [...select.options].filter(option => !option.hidden).forEach(option => {
        const item = document.createElement('button'); item.type = 'button'; item.className = 'floating-select-option';
        item.setAttribute('role', 'option'); item.setAttribute('aria-selected', String(option.selected)); item.disabled = option.disabled;
        item.textContent = option.textContent;
        if (option.selected) { const check = document.createElement('span'); check.setAttribute('aria-hidden', 'true'); check.textContent = '✓'; item.append(check); }
        item.addEventListener('click', () => { change(option.value); control.close(); trigger.focus(); });
        menu.append(item);
      });
    }
    trigger.addEventListener('click', () => menu.hidden ? control.open() : control.close());
    trigger.addEventListener('keydown', event => {
      if (event.key === 'Escape') { control.close(); return; }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault(); control.open();
        const active = menu.querySelector('[aria-selected="true"]');
        (active || menu.querySelector('button:not(:disabled)'))?.focus();
      }
    });
    menu.addEventListener('keydown', event => {
      const items = [...menu.querySelectorAll('button:not(:disabled)')]; const current = items.indexOf(document.activeElement);
      if (event.key === 'Escape') { event.preventDefault(); control.close(); trigger.focus(); }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); items[(current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus(); }
      if (event.key === 'Home') { event.preventDefault(); items[0]?.focus(); }
      if (event.key === 'End') { event.preventDefault(); items.at(-1)?.focus(); }
    });
    select.addEventListener('change', refresh);
    select.addEventListener('floating-select:refresh', refresh);
    new MutationObserver(refresh).observe(select, { childList: true, subtree: true, attributes: true });
    refresh();
  }

  function scan(root = document) { root.querySelectorAll?.('select').forEach(enhance); }
  document.addEventListener('pointerdown', event => { if (![...openMenus].some(control => event.target.closest('.floating-select') === control.wrapper)) closeAll(); });
  new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) scan(node); }))).observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', () => scan());
})();
