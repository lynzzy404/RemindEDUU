(function () {
  'use strict';
  document.querySelectorAll('[data-import-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Memproses...';
      }
      form.setAttribute('aria-busy', 'true');
    });
  });
}());
