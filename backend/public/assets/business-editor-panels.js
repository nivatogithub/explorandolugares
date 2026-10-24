(() => {
  'use strict';
  let sequence = 0;
  const groups = [];
  const init = root => {
    const repeatable = root.hasAttribute('data-product-editor');
    const panels = Array.from(root.children).filter(node => node.matches('[data-editor-panel]'));
    const list = root.querySelector(':scope > [data-editor-tabs]');
    if (!list || !panels.length) return;
    const id = `business-panels-${++sequence}`;
    list.setAttribute('role', 'tablist');
    if (repeatable) panels.forEach(panel => {
      if (Array.from(panel.querySelectorAll('input[type="text"],input:not([type]),textarea')).some(field => field.value.trim())) panel.dataset.configured = '1';
    });
    const enabled = panel => !!panel && (!repeatable || panel.dataset.configured === '1');
    let active = panels.find(enabled);
    const buttons = new Map();
    const add = root.querySelector(':scope > [data-add-product]');
    const select = panel => {
      active = panel;
      for (const [item, button] of buttons) {
        item.hidden = item !== panel || !enabled(item);
        button.hidden = !enabled(item);
        button.setAttribute('aria-selected', String(item === panel));
        button.tabIndex = item === panel ? 0 : -1;
      }
      root.querySelectorAll('[data-business-text-counter]').forEach(counter => { counter.hidden = false; });
    };
    const sync = () => {
      let count = 0;
      for (const panel of panels) {
        if (repeatable) panel.disabled = !enabled(panel);
        if (enabled(panel)) count++;
      }
      if (add) add.disabled = count >= panels.length;
      const empty = root.querySelector(':scope > [data-products-empty]');
      if (empty) empty.hidden = count !== 0;
      select(enabled(active) ? active : panels.find(enabled));
    };
    panels.forEach((panel, index) => {
      panel.id = `${id}-panel-${index}`;
      panel.setAttribute('role', 'tabpanel');
      const button = document.createElement('button');
      button.type = 'button';button.id = `${id}-tab-${index}`;
      button.textContent = panel.dataset.panelLabel;
      button.setAttribute('role', 'tab');button.setAttribute('aria-controls', panel.id);
      panel.setAttribute('aria-labelledby', button.id);
      button.addEventListener('click', () => select(panel));
      list.append(button);buttons.set(panel, button);
      panel.querySelector('[data-remove-product]')?.addEventListener('click', () => {
        const slot = panel.dataset.productSlot;
        const form = root.closest('form');
        const removal = root.querySelector(`[data-product-removal="${slot}"]`);
        if (removal) removal.disabled = false;
        panel.querySelectorAll('input,textarea,select').forEach(field => {
          field.disabled = false;
          if (field.matches('[type="checkbox"],[type="radio"]')) field.checked = false;
          else if (field.tagName === 'SELECT') field.selectedIndex = 0;
          else field.value = '';
        });
        form?.querySelector(`[data-temp-upload="editorial-product-${slot}"]`)?.remove();
        panel.querySelectorAll('[data-upload-status],img').forEach(node => node.remove());
        panel.dataset.configured = '0';sync();
        root.dispatchEvent(new Event('change', {bubbles: true}));
        (active ? buttons.get(active) : add)?.focus();
      });
    });
    list.addEventListener('keydown', event => {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
      const available = panels.filter(enabled);if (!available.length) return;
      let index = available.indexOf(active);
      index = event.key === 'Home' ? 0 : event.key === 'End' ? available.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + available.length) % available.length;
      event.preventDefault();select(available[index]);buttons.get(active).focus();
    });
    add?.addEventListener('click', () => {
      const panel = panels.find(item => !enabled(item));if (!panel) return;
      panel.dataset.configured = '1';
      const removal = root.querySelector(`[data-product-removal="${panel.dataset.productSlot}"]`);
      if (removal) removal.disabled = true;
      sync();select(panel);buttons.get(panel).focus();
    });
    root.addEventListener('invalid', event => {
      const panel = panels.find(item => item.contains(event.target));
      if (panel && enabled(panel)) select(panel);
    }, true);
    groups.push({root, panels, enabled, select});sync();
  };
  const start = () => {
    document.querySelectorAll('[data-editor-panels]').forEach(init);
    document.addEventListener('invalid', event => {
      // Reveal both the product and its language before the browser focuses an error.
      groups.filter(group => group.root.contains(event.target)).forEach(group => {
        const panel = group.panels.find(item => item.contains(event.target));
        if (panel && group.enabled(panel)) group.select(panel);
      });
    }, true);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);else start();
})();
