// Progressive enhancement: the static project cards remain visible if the API fails.
// The visual templates are trusted local markup; CMS data is inserted as text only.
(() => {
  const grid = document.querySelector('#projects .project-grid');
  if (!grid) return;

  const artwork = {
    cloud: '<div class="cloud-tiles"><span>AWS</span><span>TF</span><span>CI/CD</span><span>EC2</span></div>',
    signage: '<div class="mini-display"><div class="display-dots">● ● ●</div><div class="display-landscape"><span></span></div><div class="display-lines">▰ ▰ ▰</div></div>',
    api: '<div class="mini-api"><div class="display-dots">● ● ●</div><div class="code-line"></div><div class="code-line short"></div><div class="chart-bars"><i></i><i></i><i></i><i></i><i></i><i></i></div></div>',
    homelab: '<div class="server-stack"><div>● <span>━━━━━━━━</span></div><div>● <span>━━━━━━━━</span></div><div>● <span>━━━━━━━━</span></div></div>'
  };

  const element = (tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = value;
    return node;
  };

  function createCard(project, index, url) {
    const card = element('article', 'project-card');
    const art = element('div', 'project-art art-' + project.style);
    // Only predefined local artwork is parsed as HTML, never API-provided content.
    art.innerHTML = artwork[project.style];
    art.append(element('small', '', project.art_label));

    const body = element('div', 'project-body');
    body.append(
      element('div', 'project-type', String(index + 1).padStart(2, '0') + ' / ' + project.category),
      element('h3', '', project.title),
      element('p', '', project.description)
    );

    const tags = element('div', 'tags');
    tags.append(...project.tags.map(tag => element('span', '', tag)));

    const link = element('a', 'project-link');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.append(document.createTextNode('Explore on GitHub '), element('span', '', '↗'));
    body.append(tags, link);
    card.append(art, body);
    return card;
  }

  const preview = document.documentElement.dataset.cmsPreview === 'true';
  fetch(preview ? '/admin/api/preview-projects.php' : '/admin/api/projects.php', {
    cache: 'no-store',
    credentials: preview ? 'same-origin' : 'omit'
  })
    .then(response => {
      if (!response.ok) throw new Error('Projects unavailable');
      return response.json();
    })
    .then(projects => {
      if (!Array.isArray(projects) || projects.length > 20) return;
      const cards = [];
      for (const [index, project] of projects.entries()) {
        if (!project || typeof project !== 'object' || !Object.hasOwn(artwork, project.style)) return;
        const fields = ['title', 'description', 'category', 'art_label', 'url'];
        if (fields.some(field => typeof project[field] !== 'string')) return;
        if (!Array.isArray(project.tags) || project.tags.length < 1 || project.tags.length > 6 ||
            project.tags.some(tag => typeof tag !== 'string')) return;

        let parsed;
        try {
          parsed = new URL(project.url);
          if (!['https:', 'http:'].includes(parsed.protocol) || parsed.username || parsed.password) return;
        } catch { return; }

        cards.push(createCard(project, index, parsed.href));
      }
      grid.replaceChildren(...cards);
    })
    .catch(() => {
      if (preview) {
        document.body.dataset.previewError = 'true';
        const banner = document.querySelector('.cms-preview-banner');
        const message = banner?.querySelector('strong');
        if (message) message.textContent = 'Preview could not load — sign in again or reload';
      }
      // Keep static cards when the CMS endpoint cannot be reached.
    });
})();
