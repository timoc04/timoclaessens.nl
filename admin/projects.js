document.querySelectorAll('[data-confirm-delete]').forEach(button => {
  button.addEventListener('click', event => {
    const title = button.dataset.confirmDelete || 'this project';
    if (!window.confirm('Delete "' + title + '" from your live website?')) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('[data-confirm-discard]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Discard all unpublished project changes?')) event.preventDefault();
  });
});

document.querySelectorAll('form').forEach(form => {
  const button = form.querySelector('button[type="submit"]');
  if (!button || !['Save project draft', 'Add to draft ↗'].includes(button.textContent.trim())) return;
  window.CmsForms.enableWhenChanged(
    form,
    button,
    'input[name^="project["], textarea[name^="project["], select[name^="project["]'
  );
});
