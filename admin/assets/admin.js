(() => {
  const btn = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.admin-sidebar');
  if (btn && sidebar) {
    btn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (window.innerWidth > 880) return;
      if (!sidebar.contains(e.target) && !btn.contains(e.target)) sidebar.classList.remove('open');
    });
  }

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      const msg = el.getAttribute('data-confirm') || 'Продолжить?';
      if (!confirm(msg)) e.preventDefault();
    });
  });


  document.querySelectorAll('.settings-section[data-settings-section]').forEach(section => {
    const key = 'dagstudio.settings.' + section.dataset.settingsSection;
    const saved = localStorage.getItem(key);
    if (saved === 'open') section.open = true;
    if (saved === 'closed') section.open = false;
    section.addEventListener('toggle', () => {
      localStorage.setItem(key, section.open ? 'open' : 'closed');
    });
  });

  const themeKeys = ['accent', 'bg', 'panel', 'text'];
  const applyThemePreview = () => {
    const values = {};
    themeKeys.forEach(key => {
      const input = document.getElementById('theme_' + key);
      if (input && /^#[0-9a-fA-F]{6}$/.test(input.value)) values[key] = input.value;
    });
    if (values.accent) document.documentElement.style.setProperty('--admin-accent', values.accent);
    if (values.bg) document.documentElement.style.setProperty('--admin-bg', values.bg);
    if (values.panel) document.documentElement.style.setProperty('--admin-panel', values.panel);
    if (values.text) document.documentElement.style.setProperty('--admin-text', values.text);
  };

  document.querySelectorAll('[data-theme-preset]').forEach(card => {
    const radio = card.querySelector('input[type="radio"]');
    if (!radio) return;
    radio.addEventListener('change', () => {
      if (!radio.checked) return;
      const palette = {
        accent: card.dataset.accent,
        bg: card.dataset.bg,
        panel: card.dataset.panel,
        text: card.dataset.text
      };
      themeKeys.forEach(key => {
        const textInput = document.getElementById('theme_' + key);
        const picker = document.getElementById('theme_' + key + '_picker');
        if (textInput && palette[key]) textInput.value = palette[key];
        if (picker && palette[key]) picker.value = palette[key];
      });
      applyThemePreview();
    });
  });

  themeKeys.forEach(key => {
    const textInput = document.getElementById('theme_' + key);
    const picker = document.getElementById('theme_' + key + '_picker');
    const customRadio = document.querySelector('input[name="theme_scheme"][value="custom"]');
    if (picker && textInput) {
      picker.addEventListener('input', () => {
        textInput.value = picker.value;
        if (customRadio) customRadio.checked = true;
        applyThemePreview();
      });
      textInput.addEventListener('input', () => {
        if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
          picker.value = textInput.value;
          if (customRadio) customRadio.checked = true;
          applyThemePreview();
        }
      });
    }
  });

  document.querySelectorAll('[data-logo-input]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      const previewId = input.dataset.preview;
      const preview = previewId ? document.getElementById(previewId) : null;
      if (!file || !preview) return;
      const url = URL.createObjectURL(file);
      preview.src = url;
      preview.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
    });
  });

  const menuEditor = document.querySelector('[data-menu-editor]');
  const menuTemplate = document.getElementById('menu-row-template');
  const menuAdd = document.querySelector('[data-menu-add]');
  const settingsForm = document.querySelector('.settings-form');

  const renumberMenu = () => {
    if (!menuEditor) return;
    [...menuEditor.querySelectorAll('[data-menu-row]')].forEach((row, index) => {
      const label = row.querySelector('input[name^="menu_label"], [data-menu-field="label"]');
      const url = row.querySelector('input[name^="menu_url"], [data-menu-field="url"]');
      const visible = row.querySelector('input[name^="menu_visible"], [data-menu-field="visible"]');
      const newTab = row.querySelector('input[name^="menu_new_tab"], [data-menu-field="new_tab"]');
      if (label) label.name = 'menu_label[' + index + ']';
      if (url) url.name = 'menu_url[' + index + ']';
      if (visible) visible.name = 'menu_visible[' + index + ']';
      if (newTab) newTab.name = 'menu_new_tab[' + index + ']';

      const up = row.querySelector('[data-menu-up]');
      const down = row.querySelector('[data-menu-down]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === menuEditor.querySelectorAll('[data-menu-row]').length - 1;
    });
  };

  const bindMenuRow = row => {
    const remove = row.querySelector('[data-menu-delete]');
    const up = row.querySelector('[data-menu-up]');
    const down = row.querySelector('[data-menu-down]');

    remove?.addEventListener('click', () => {
      row.remove();
      renumberMenu();
    });
    up?.addEventListener('click', () => {
      const prev = row.previousElementSibling;
      if (prev) menuEditor.insertBefore(row, prev);
      renumberMenu();
    });
    down?.addEventListener('click', () => {
      const next = row.nextElementSibling;
      if (next) menuEditor.insertBefore(next, row);
      renumberMenu();
    });
  };

  if (menuEditor) {
    menuEditor.querySelectorAll('[data-menu-row]').forEach(bindMenuRow);
    renumberMenu();
  }

  menuAdd?.addEventListener('click', () => {
    if (!menuEditor || !menuTemplate) return;
    if (menuEditor.querySelectorAll('[data-menu-row]').length >= 12) {
      alert('Можно добавить не более 12 пунктов меню.');
      return;
    }
    const fragment = menuTemplate.content.cloneNode(true);
    const row = fragment.querySelector('[data-menu-row]');
    if (!row) return;
    menuEditor.appendChild(fragment);
    const added = menuEditor.lastElementChild;
    bindMenuRow(added);
    renumberMenu();
    added.querySelector('input[data-menu-field="label"]')?.focus();
  });

  settingsForm?.addEventListener('submit', renumberMenu);

  applyThemePreview();
})();