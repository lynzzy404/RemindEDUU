(function () {
  'use strict';

  var shell = document.querySelector('[data-sidebar-shell]');
  if (!shell) {
    return;
  }

  var drawer = shell.querySelector('[data-sidebar-drawer]');
  var toggle = shell.querySelector('[data-sidebar-toggle]');
  var closeButton = shell.querySelector('[data-sidebar-close]');
  var overlay = shell.querySelector('[data-sidebar-overlay]');
  var mediaQuery = window.matchMedia('(min-width: 1024px)');
  var lastFocusedElement = null;

  if (!drawer || !toggle || !closeButton || !overlay) {
    return;
  }

  document.documentElement.classList.add('js-sidebar');

  function focusableElements() {
    return Array.prototype.slice.call(drawer.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    ));
  }

  function setOpen(isOpen, restoreFocus) {
    shell.classList.toggle('is-open', isOpen);
    document.body.classList.toggle('sidebar-is-open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    overlay.hidden = !isOpen;

    if (isOpen) {
      lastFocusedElement = document.activeElement;
      closeButton.focus();
    } else if (restoreFocus !== false && lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      lastFocusedElement.focus();
      lastFocusedElement = null;
    } else if (!isOpen) {
      lastFocusedElement = null;
    }
  }

  function close() {
    setOpen(false);
  }

  function trapFocus(event) {
    if (!shell.classList.contains('is-open') || event.key !== 'Tab') {
      return;
    }

    var elements = focusableElements();
    if (!elements.length) {
      event.preventDefault();
      closeButton.focus();
      return;
    }

    var first = elements[0];
    var last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  toggle.addEventListener('click', function () {
    setOpen(!shell.classList.contains('is-open'));
  });
  closeButton.addEventListener('click', close);
  overlay.addEventListener('click', close);
  drawer.querySelectorAll('[data-sidebar-link]').forEach(function (link) {
    link.addEventListener('click', close);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && shell.classList.contains('is-open')) {
      close();
      return;
    }
    trapFocus(event);
  });

  function handleResize(event) {
    if (event.matches) {
      setOpen(false, false);
    }
  }

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 1024) {
      setOpen(false, false);
    }
  });

  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', handleResize);
  } else {
    mediaQuery.addListener(handleResize);
  }
}());
