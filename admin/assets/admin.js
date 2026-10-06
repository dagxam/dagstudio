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

  applyThemePreview();
})();