(function () {
  'use strict';

  function setupDropdown(root) {
    var trigger = root.querySelector('[data-dropdown-trigger]');
    var menu = root.querySelector('[data-dropdown-menu]');
    var value = root.querySelector('[data-dropdown-value]');
    var label = root.querySelector('[data-selected-label]');
    var chip = root.querySelector('[data-selected-chip]');
    var options = Array.prototype.slice.call(root.querySelectorAll('[role="option"]'));
    var typeahead = '';
    var typeaheadTimer;
    var activeIndex = options.findIndex(function (option) {
      return option.getAttribute('aria-selected') === 'true';
    });

    if (!trigger || !menu || !value || !label || !chip || options.length === 0) {
      return;
    }

    function setActive(index) {
      activeIndex = Math.max(0, Math.min(index, options.length - 1));
      options.forEach(function (option, optionIndex) {
        option.classList.toggle('is-active', optionIndex === activeIndex);
      });
      menu.setAttribute('aria-activedescendant', options[activeIndex].id || '');
      options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function selectOption(option) {
      value.value = option.getAttribute('data-value') || '';
      label.textContent = option.textContent;
      chip.setAttribute('data-color', option.getAttribute('data-color') || '');
      options.forEach(function (item) {
        item.setAttribute('aria-selected', item === option ? 'true' : 'false');
      });
    }

    function close() {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    function open() {
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      setActive(activeIndex);
      menu.focus();
    }

    function chooseAndClose() {
      selectOption(options[activeIndex]);
      close();
      trigger.focus();
    }

    trigger.addEventListener('click', function () {
      if (menu.hidden) {
        open();
      } else {
        close();
      }
    });

    trigger.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        if (menu.hidden) {
          open();
        } else if (event.key !== 'Enter' && event.key !== ' ') {
          setActive(activeIndex + 1);
        } else {
          chooseAndClose();
        }
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (menu.hidden) {
          open();
        } else {
          setActive(activeIndex - 1);
        }
      } else if (event.key === 'Escape') {
        close();
      }
    });

    menu.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Home') {
        event.preventDefault();
        setActive(0);
      } else if (event.key === 'End') {
        event.preventDefault();
        setActive(options.length - 1);
      } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        chooseAndClose();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        close();
        trigger.focus();
      } else if (event.key.length === 1 && /\S/.test(event.key)) {
        typeahead += event.key.toLowerCase();
        window.clearTimeout(typeaheadTimer);
        typeaheadTimer = window.setTimeout(function () {
          typeahead = '';
        }, 500);
        var match = options.findIndex(function (option) {
          return option.textContent.trim().toLowerCase().indexOf(typeahead) === 0;
        });
        if (match >= 0) {
          setActive(match);
        }
      }
    });

    options.forEach(function (option, index) {
      if (!option.id) {
        option.id = root.id ? root.id + '-option-' + index : 'category-option-' + index;
      }
      option.addEventListener('click', function () {
        selectOption(option);
        close();
        trigger.focus();
      });
      option.addEventListener('mousemove', function () {
        setActive(index);
      });
    });

    document.addEventListener('click', function (event) {
      if (!root.contains(event.target)) {
        close();
      }
    });
  }

  document.querySelectorAll('[data-category-dropdown]').forEach(setupDropdown);

  document.querySelectorAll('[data-delete-dialog]').forEach(function (button) {
    button.addEventListener('click', function () {
      var dialog = document.getElementById(button.getAttribute('data-delete-dialog'));
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });
  });

  document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
    button.addEventListener('click', function () {
      var dialog = button.closest('dialog');
      if (dialog) {
        dialog.close();
      }
    });
  });
}());
