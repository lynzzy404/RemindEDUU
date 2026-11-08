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

  document.querySelectorAll('[data-live-search]').forEach(function (input) {
    var results = document.querySelector('[data-live-results]');
    if (!results) {
      return;
    }

    var items = Array.prototype.slice.call(results.querySelectorAll('.task-card'));
    var empty = document.createElement('p');
    empty.className = 'live-search-empty';
    empty.setAttribute('role', 'status');
    empty.textContent = 'Tidak ada tugas yang cocok.';
    empty.hidden = true;
    results.appendChild(empty);

    var updateResults = function () {
      var query = input.value.trim().toLocaleLowerCase();
      var visible = 0;

      items.forEach(function (item) {
        var matches = query === '' || item.textContent.toLocaleLowerCase().indexOf(query) !== -1;
        item.hidden = !matches;
        if (matches) {
          visible += 1;
        }
      });

      empty.hidden = visible !== 0;
    };

    input.addEventListener('input', updateResults);
    updateResults();
  });
}());
