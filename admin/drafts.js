document.querySelectorAll('[data-confirm-discard]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Discard the saved draft? This cannot be undone.')) {
      event.preventDefault();
    }
  });
});

const draftEditor = document.querySelector('[data-draft-editor]');
if (draftEditor) {
  window.CmsForms.enableWhenChanged(
    draftEditor,
    draftEditor.querySelector('button[name="action"][value="save_draft"]'),
    '[name^="content["]'
  );
}
