document.querySelectorAll('[data-confirm-discard]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Discard the saved draft? This cannot be undone.')) {
      event.preventDefault();
    }
  });
});
