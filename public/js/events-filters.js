/**
 * Live-submit the /events filter form on change. Without JS, the Apply button
 * is visible and the form submits normally; with JS, changes submit immediately
 * and Apply is hidden via CSS ([data-live-submit="on"]).
 */
(() => {
  const form = document.querySelector('form.event-filters[data-live-submit]');
  if (!form) return;
  form.dataset.liveSubmit = 'on';

  const submit = () => form.submit();
  let debounce;

  form.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.type === 'search') return;
    submit();
  });

  const search = form.querySelector('input[type="search"]');
  if (search) {
    search.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(submit, 350);
    });
  }
})();
