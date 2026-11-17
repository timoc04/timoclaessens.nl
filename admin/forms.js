// Shared, dependency-free dirty-state handling for CMS forms.
window.CmsForms = Object.freeze({
  enableWhenChanged(form, button, selector) {
    if (!form || !button) return;
    const fields = Array.from(form.querySelectorAll(selector));
    const initial = fields.map(field => field.value);
    const update = () => {
      button.disabled = !fields.some((field, index) => field.value !== initial[index]);
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    window.addEventListener('pageshow', update);
    update();
  }
});
