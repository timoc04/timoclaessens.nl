// Confirm irreversible project deletion; server-side validation remains authoritative.
document.querySelectorAll('[data-confirm-delete]').forEach(button => {
  button.addEventListener('click', event => {
    const title = button.dataset.confirmDelete || 'this project';
    if (!window.confirm('Delete "' + title + '" from your live website?')) {
      event.preventDefault();
    }
  });
});
