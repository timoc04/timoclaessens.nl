// Visible reminder that this is an authenticated draft preview, not the live site.
(() => {
  const banner = document.createElement('aside');
  banner.className = 'cms-preview-banner';
  banner.setAttribute('role', 'status');
  const title = document.createElement('strong');
  title.textContent = 'Draft preview — not published';
  const back = document.createElement('a');
  back.href = '/admin/';
  back.textContent = 'Back to CMS';
  const projects = document.createElement('a');
  projects.href = '/admin/projects.php';
  projects.textContent = 'Manage projects';
  banner.append(title, back, projects);
  document.body.prepend(banner);
  document.body.classList.add('cms-preview-mode');
})();
