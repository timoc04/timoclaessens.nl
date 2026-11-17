document.querySelectorAll('[data-confirm-discard]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Discard the saved draft? This cannot be undone.')) {
      event.preventDefault();
    }
  });
});

// Enable saving only when the editable content differs from the loaded draft.
const draftEditor = document.querySelector('[data-draft-editor]');
if (draftEditor) {
  const saveButton = draftEditor.querySelector('button[name="action"][value="save_draft"]');
  const fields = Array.from(draftEditor.querySelectorAll('[name^="content["]'));
  const originalValues = fields.map(field => field.value);
  const updateSaveState = () => {
    const changed = fields.some((field, index) => field.value !== originalValues[index]);
    saveButton.disabled = !changed;
  };
  draftEditor.addEventListener('input', updateSaveState);
  draftEditor.addEventListener('change', updateSaveState);
  // Handle browser form restoration (for example when navigating back).
  window.addEventListener('pageshow', updateSaveState);
  updateSaveState();
}
