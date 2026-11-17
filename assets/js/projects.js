// Progressive enhancement: static project cards in index.html remain the fallback.
// Project records come from the read-only CMS endpoint; no HTML is accepted.
(() => {
  const grid = document.querySelector('#projects .project-grid');
  if (!grid) return;

  const templates = new Map();
  ['cloud', 'signage', 'api', 'homelab'].forEach((style, index) => {
    const card = grid.children[index];
    if (card) templates.set(style, card.cloneNode(true));
  });

  fetch(document.documentElement.dataset.cmsPreview === 'true' ? '/admin/api/preview-projects.php' : '/admin/api/projects.php', { cache: 'no-store', credentials: document.documentElement.dataset.cmsPreview === 'true' ? 'same-origin' : 'omit' })
    .then(response => {
      if (!response.ok) throw new Error('Projects unavailable');
      return response.json();
    })
    .then(projects => {
      if (!Array.isArray(projects) || projects.length > 20) return;
      const cards = [];
      for (const [index, project] of projects.entries()) {
        if (!project || typeof project !== 'object' || !templates.has(project.style)) return;
        const fields = ['title', 'description', 'category', 'art_label', 'url'];
        if (fields.some(field => typeof project[field] !== 'string')) return;
        if (!Array.isArray(project.tags) || project.tags.length < 1 || project.tags.length > 6 ||
            project.tags.some(tag => typeof tag !== 'string')) return;

        let parsed;
        try {
          parsed = new URL(project.url);
          if (!['https:', 'http:'].includes(parsed.protocol) || parsed.username || parsed.password) return;
        } catch { return; }

        const card = templates.get(project.style).cloneNode(true);
        card.querySelector('h3').textContent = project.title;
        card.querySelector('.project-body > p').textContent = project.description;
        card.querySelector('.project-type').textContent =
          String(index + 1).padStart(2, '0') + ' / ' + project.category;
        card.querySelector('.project-art small').textContent = project.art_label;
        const link = card.querySelector('.project-link');
        link.href = parsed.href;
        link.removeAttribute('data-cms-url');
        card.querySelectorAll('[data-cms]').forEach(node => node.removeAttribute('data-cms'));
        const tags = card.querySelector('.tags');
        tags.removeAttribute('data-cms-tags');
        tags.replaceChildren(...project.tags.map(tag => {
          const span = document.createElement('span');
          span.textContent = tag;
          return span;
        }));
        cards.push(card);
      }
      grid.replaceChildren(...cards);
    })
    .catch(() => {
      // Keep static cards when the CMS endpoint cannot be reached.
    });
})();
