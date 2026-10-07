(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    const mobileBreakpoint = 900;
    const setNavOpen = open => {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('nav-open', open && window.innerWidth <= mobileBreakpoint);
    };

    toggle.addEventListener('click', () => setNavOpen(!nav.classList.contains('is-open')));
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setNavOpen(false)));

    document.addEventListener('click', event => {
      if (window.innerWidth > mobileBreakpoint || !nav.classList.contains('is-open')) return;
      if (!nav.contains(event.target) && !toggle.contains(event.target)) setNavOpen(false);
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        setNavOpen(false);
        toggle.focus();
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > mobileBreakpoint) setNavOpen(false);
    }, { passive: true });
  }

  const adminAccess = document.querySelector('[data-admin-access]');
  if (adminAccess) {
    const trigger = adminAccess.querySelector('[data-admin-trigger]');
    const label = adminAccess.querySelector('[data-admin-label]');
    const menu = adminAccess.querySelector('[data-admin-menu]');

    const setAdminMenuOpen = open => {
      if (!trigger || !menu || !adminAccess.classList.contains('is-authenticated')) return;
      menu.hidden = !open;
      adminAccess.classList.toggle('is-open', open);
      trigger.setAttribute('aria-expanded', String(open));
    };

    fetch('/admin/session-status.php', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    })
      .then(response => response.ok ? response.json() : null)
      .then(data => {
        if (!data?.authenticated || !trigger || !label || !menu) return;
        adminAccess.classList.add('is-authenticated');
        label.textContent = 'Администратор';
        trigger.setAttribute('href', '#');
        trigger.setAttribute('aria-label', 'Меню администратора');
        menu.hidden = true;
      })
      .catch(() => {});

    trigger?.addEventListener('click', event => {
      if (!adminAccess.classList.contains('is-authenticated')) return;
      event.preventDefault();
      setAdminMenuOpen(!adminAccess.classList.contains('is-open'));
    });

    document.addEventListener('click', event => {
      if (!adminAccess.classList.contains('is-open')) return;
      if (!adminAccess.contains(event.target)) setAdminMenuOpen(false);
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && adminAccess.classList.contains('is-open')) {
        setAdminMenuOpen(false);
        trigger?.focus();
      }
    });

    window.addEventListener('resize', () => {
      if (adminAccess.classList.contains('is-open')) setAdminMenuOpen(false);
    }, { passive: true });
  }

  const reveal = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    reveal.forEach(el => observer.observe(el));
  } else {
    reveal.forEach(el => el.classList.add('is-visible'));
  }

  const modal = document.getElementById('orderModal');
  const modalStatus = modal?.querySelector('.order-form-status');
  const firstModalInput = modal?.querySelector('input[name="name"]');
  const openModal = () => {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    window.setTimeout(() => firstModalInput?.focus(), 80);
  };
  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  };

  document.querySelectorAll('[data-order-open]').forEach(button => button.addEventListener('click', openModal));
  document.querySelectorAll('[data-order-close]').forEach(button => button.addEventListener('click', closeModal));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && modal?.classList.contains('is-open')) closeModal();
  });

  document.querySelectorAll('.request-form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (!button || !form.checkValidity()) return;
      button.disabled = true;
      button.classList.add('is-loading');
      button.dataset.originalText = button.textContent;
      button.textContent = 'Отправляем...';
    });
  });

  const params = new URLSearchParams(location.search);
  const sent = params.get('sent');
  const source = params.get('source') || 'contact';
  const contactStatus = document.querySelector('#contacts .form-status');
  const toast = document.getElementById('siteToast');

  const showToast = (text, type = 'success') => {
    if (!toast) return;
    toast.textContent = text;
    toast.className = 'site-toast is-visible ' + type;
    window.setTimeout(() => toast.classList.remove('is-visible'), 5500);
  };

  if (sent === '1') {
    const message = 'Обращение принято. Мы рассмотрим его и свяжемся с вами.';
    if (source === 'modal') {
      showToast(message, 'success');
      closeModal();
    } else if (contactStatus) {
      contactStatus.textContent = message;
      contactStatus.classList.add('success');
      showToast(message, 'success');
    }
    history.replaceState({}, '', location.pathname + (source === 'contact' ? '#contacts' : ''));
  }

  if (sent === '0') {
    const message = 'Не удалось отправить обращение. Проверьте данные и попробуйте ещё раз.';
    if (source === 'modal') {
      openModal();
      if (modalStatus) {
        modalStatus.textContent = message;
        modalStatus.classList.add('error');
      }
    } else if (contactStatus) {
      contactStatus.textContent = message;
      contactStatus.classList.add('error');
    }
    showToast(message, 'error');
    history.replaceState({}, '', location.pathname + (source === 'contact' ? '#contacts' : ''));
  }
})();