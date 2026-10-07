(() => {
  const btn = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.admin-sidebar');
  if (btn && sidebar) {
    const mobileBreakpoint = 1024;
    const setSidebarOpen = open => {
      sidebar.classList.toggle('open', open);
      document.body.classList.toggle('admin-sidebar-open', open && window.innerWidth <= mobileBreakpoint);
      btn.setAttribute('aria-expanded', String(open));
    };

    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', () => setSidebarOpen(!sidebar.classList.contains('open')));

    document.addEventListener('click', e => {
      if (window.innerWidth > mobileBreakpoint || !sidebar.classList.contains('open')) return;
      if (!sidebar.contains(e.target) && !btn.contains(e.target)) setSidebarOpen(false);
    });

    sidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= mobileBreakpoint) setSidebarOpen(false);
      });
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        setSidebarOpen(false);
        btn.focus();
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > mobileBreakpoint) setSidebarOpen(false);
    }, { passive: true });
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
    menuEditor.appendChild(fragment);
    const added = menuEditor.lastElementChild;
    if (!added) return;
    bindMenuRow(added);
    renumberMenu();
    added.querySelector('input[data-menu-field="label"]')?.focus();
  });

  const addMenuRow = (label = '', url = '#') => {
    if (!menuEditor || !menuTemplate) return null;
    if (menuEditor.querySelectorAll('[data-menu-row]').length >= 12) {
      alert('Можно добавить не более 12 пунктов меню.');
      return null;
    }
    const fragment = menuTemplate.content.cloneNode(true);
    menuEditor.appendChild(fragment);
    const added = menuEditor.lastElementChild;
    if (!added) return null;
    bindMenuRow(added);
    const labelInput = added.querySelector('[data-menu-field="label"]');
    const urlInput = added.querySelector('[data-menu-field="url"]');
    if (labelInput) labelInput.value = label;
    if (urlInput) urlInput.value = url;
    renumberMenu();
    return added;
  };

  const pageSelect = document.querySelector('[data-menu-page-select]');
  const pageAdd = document.querySelector('[data-menu-add-page]');
  pageAdd?.addEventListener('click', () => {
    if (!pageSelect || !pageSelect.value) return;
    const selected = pageSelect.options[pageSelect.selectedIndex];
    const title = selected?.dataset?.title || selected?.textContent || 'Страница';
    const row = addMenuRow(title.trim(), pageSelect.value);
    row?.querySelector('input[name^="menu_label"]')?.focus();
    pageSelect.value = '';
  });

  settingsForm?.addEventListener('submit', renumberMenu);

  const bbTextarea = document.querySelector('[data-bb-textarea]');
  const bbToolbar = document.querySelector('[data-bb-toolbar]');
  if (bbTextarea && bbToolbar) {
    const wrapSelection = (open, close, placeholder = 'Текст') => {
      const start = bbTextarea.selectionStart ?? bbTextarea.value.length;
      const end = bbTextarea.selectionEnd ?? start;
      const selected = bbTextarea.value.slice(start, end) || placeholder;
      const replacement = open + selected + close;
      bbTextarea.setRangeText(replacement, start, end, 'select');
      bbTextarea.focus();
      const selectionStart = start + open.length;
      bbTextarea.setSelectionRange(selectionStart, selectionStart + selected.length);
    };

    bbToolbar.querySelectorAll('[data-bb-tag]').forEach(button => {
      button.addEventListener('click', () => {
        const tag = button.dataset.bbTag;
        if (!tag) return;
        wrapSelection('[' + tag + ']', '[/' + tag + ']', tag === 'code' ? 'Код' : 'Текст');
      });
    });

    bbToolbar.querySelector('[data-bb-link]')?.addEventListener('click', () => {
      const url = prompt('Введите ссылку:', 'https://');
      if (!url) return;
      wrapSelection('[url=' + url.trim() + ']', '[/url]', 'Текст ссылки');
    });

    bbToolbar.querySelector('[data-bb-image]')?.addEventListener('click', () => {
      const url = prompt('Введите прямую ссылку на изображение:', 'https://');
      if (!url) return;
      const start = bbTextarea.selectionStart ?? bbTextarea.value.length;
      bbTextarea.setRangeText('[img]' + url.trim() + '[/img]', start, bbTextarea.selectionEnd ?? start, 'end');
      bbTextarea.focus();
    });

    bbToolbar.querySelector('[data-bb-color]')?.addEventListener('click', () => {
      const color = prompt('Цвет в формате #RRGGBB:', '#c96f41');
      if (!color || !/^#[0-9a-fA-F]{6}$/.test(color.trim())) return;
      wrapSelection('[color=' + color.trim() + ']', '[/color]', 'Цветной текст');
    });

    bbToolbar.querySelector('[data-bb-list]')?.addEventListener('click', () => {
      const start = bbTextarea.selectionStart ?? bbTextarea.value.length;
      const end = bbTextarea.selectionEnd ?? start;
      const selected = bbTextarea.value.slice(start, end).trim();
      const content = selected
        ? selected.split(/\n+/).map(line => '[*]' + line.replace(/^\[\*\]/, '')).join('\n')
        : '[*]Первый пункт\n[*]Второй пункт';
      bbTextarea.setRangeText('[list]\n' + content + '\n[/list]', start, end, 'select');
      bbTextarea.focus();
    });
  }

  const sidebarOrderList = document.querySelector('[data-sidebar-order-list]');
  if (sidebarOrderList) {
    let dragged = null;

    const refreshSidebarOrderButtons = () => {
      const rows = [...sidebarOrderList.querySelectorAll('[data-sidebar-order-row]')];
      rows.forEach((row, index) => {
        const up = row.querySelector('[data-sidebar-up]');
        const down = row.querySelector('[data-sidebar-down]');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === rows.length - 1;
      });
    };

    sidebarOrderList.querySelectorAll('[data-sidebar-order-row]').forEach(row => {
      row.querySelector('[data-sidebar-up]')?.addEventListener('click', () => {
        const prev = row.previousElementSibling;
        if (prev) sidebarOrderList.insertBefore(row, prev);
        refreshSidebarOrderButtons();
      });

      row.querySelector('[data-sidebar-down]')?.addEventListener('click', () => {
        const next = row.nextElementSibling;
        if (next) sidebarOrderList.insertBefore(next, row);
        refreshSidebarOrderButtons();
      });

      row.addEventListener('dragstart', event => {
        dragged = row;
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', 'sidebar-order');
        }
      });

      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        dragged = null;
        refreshSidebarOrderButtons();
      });

      row.addEventListener('dragover', event => {
        if (!dragged || dragged === row) return;
        event.preventDefault();
        const rect = row.getBoundingClientRect();
        const before = event.clientY < rect.top + rect.height / 2;
        if (before) sidebarOrderList.insertBefore(dragged, row);
        else sidebarOrderList.insertBefore(dragged, row.nextSibling);
      });
    });

    refreshSidebarOrderButtons();
  }

  applyThemePreview();
})();