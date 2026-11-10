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

  document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
    var rawTargets = toggle.getAttribute('data-target') || toggle.getAttribute('aria-controls') || '';
    var targetIds = rawTargets.trim().split(/[\s,]+/);
    var inputs = [];

    targetIds.forEach(function (id) {
      if (id) {
        var el = document.getElementById(id);
        if (el && inputs.indexOf(el) === -1) {
          inputs.push(el);
        }
      }
    });

    if (inputs.length === 0) {
      var wrapper = toggle.closest('.password-input-wrapper');
      var fallbackInput = wrapper ? wrapper.querySelector('input') : null;
      if (fallbackInput) {
        inputs.push(fallbackInput);
      }
    }

    if (inputs.length === 0) {
      return;
    }

    function setVisibility(isVisible) {
      var nextType = isVisible ? 'text' : 'password';
      inputs.forEach(function (input) {
        input.setAttribute('type', nextType);
      });
      toggle.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
      toggle.setAttribute('aria-label', isVisible ? 'Sembunyikan password' : 'Tampilkan password');
    }

    toggle.removeAttribute('hidden');
    toggle.hidden = false;

    toggle.addEventListener('click', function () {
      var isCurrentlyVisible = toggle.getAttribute('aria-pressed') === 'true';
      var activeEl = document.activeElement;
      var activeIndex = inputs.indexOf(activeEl);
      var selStart = null;
      var selEnd = null;

      if (activeIndex !== -1) {
        try {
          selStart = activeEl.selectionStart;
          selEnd = activeEl.selectionEnd;
        } catch (e) {}
      }

      setVisibility(!isCurrentlyVisible);

      if (activeIndex !== -1 && activeEl) {
        activeEl.focus();
        if (selStart !== null && selEnd !== null) {
          try {
            activeEl.setSelectionRange(selStart, selEnd);
          } catch (e) {}
        }
      }
    });

    var form = toggle.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        setVisibility(false);
      });
    }

    window.addEventListener('pageshow', function () {
      setVisibility(false);
    });
  });
}());
