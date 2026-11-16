// Optional behaviour only; edit all page content in index.html.
const menuButton = document.querySelector('.menu-button');
const nav = document.querySelector('#nav');
if (menuButton && nav) {
  const closeMenu = () => {
    nav.classList.remove('open');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Open navigation');
  };
  menuButton.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') !== 'true';
    nav.classList.toggle('open', open);
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  });
  nav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeMenu();
  });
  document.addEventListener('click', event => {
    if (!nav.contains(event.target) && !menuButton.contains(event.target)) closeMenu();
  });
}
const year = document.querySelector('#year');
if (year) year.textContent = String(new Date().getFullYear());

// Load safe, plain-text portfolio updates. The original HTML remains a fallback.
const cmsElements = document.querySelectorAll('[data-cms]');
if (cmsElements.length) {
  fetch('/admin/api/content.php', { cache: 'no-store', credentials: 'omit' })
    .then(response => {
      if (!response.ok) throw new Error('CMS content unavailable');
      return response.json();
    })
    .then(content => {
      if (!content || typeof content !== 'object' || Array.isArray(content)) return;
      cmsElements.forEach(element => {
        const key = element.getAttribute('data-cms');
        if (typeof content[key] === 'string' && content[key].trim() !== '') {
          element.textContent = content[key];
        }
      });
    })
    .catch(() => {
      // Keep the content already in index.html when the CMS API is unavailable.
    });
}
