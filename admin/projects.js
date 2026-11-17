// Confirm irreversible project deletion; server-side validation remains authoritative.
document.querySelectorAll('[data-confirm-delete]').forEach(button => {
  button.addEventListener('click', event => {
    const title = button.dataset.confirmDelete || 'this project';
    if (!window.confirm('Delete "' + title + '" from your live website?')) {
      event.preventDefault();
    }
  });
});

// Discarding a draft must be intentional.
document.querySelectorAll('[data-confirm-discard]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Discard all unpublished project changes?')) event.preventDefault();
  });
});

// Save buttons only become available after a project form is changed.
document.querySelectorAll('form').forEach(form => {
  const button = form.querySelector('button[type="submit"]');
  if (!button || !['Save project draft', 'Add to draft ↗'].includes(button.textContent.trim())) return;
  const fields = Array.from(form.querySelectorAll('input[name^="project["], textarea[name^="project["], select[name^="project["]'));
  const original = fields.map(field => field.value);
  const update = () => { button.disabled = !fields.some((field, i) => field.value !== original[i]); };
  form.addEventListener('input', update);
  form.addEventListener('change', update);
  window.addEventListener('pageshow', update);
  update();
});
