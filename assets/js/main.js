(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }));
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

  const params = new URLSearchParams(location.search);
  const status = document.querySelector('.form-status');
  if (status && params.get('sent') === '1') {
    status.textContent = 'Заявка отправлена. Мы свяжемся с вами.';
    history.replaceState({}, '', location.pathname + location.hash);
  }
  if (status && params.get('sent') === '0') {
    status.textContent = 'Не удалось отправить заявку. Напишите на admin@dagstudio.ru.';
  }
})();