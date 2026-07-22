(function () {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function showLoader(message) {
    var overlay = document.getElementById('simpegGlobalLoader') || document.getElementById('simpeg-loading');
    if (!overlay) return;
    var text = overlay.querySelector('[data-loading-text]') || overlay.querySelector('p');
    if (text && message) {
      text.textContent = message;
    }
    overlay.classList.add('is-active');
  }

  function hideLoader() {
    var overlay = document.getElementById('simpegGlobalLoader') || document.getElementById('simpeg-loading');
    if (!overlay) return;
    overlay.classList.remove('is-active');
  }

  function isDesktopViewport() {
    return window.innerWidth >= 992;
  }

  function initSidebarMenu() {
    var body = document.body;
    var sidebar = document.querySelector('.simpeg-sidebar-panel');
    if (!sidebar) return;
    var parentMenuLinks = sidebar.querySelectorAll('[data-simpeg-submenu-toggle]');
    var navigableSidebarLinks = sidebar.querySelectorAll('a[href]:not([href="#"])');
    var sidebarToggles = document.querySelectorAll('[data-simpeg-sidebar-toggle]');
    var sidebarClosers = document.querySelectorAll('[data-simpeg-sidebar-close]');
    var sidebarCollapseTimer = null;

    function setAutoSidebarMode() {
      if (isDesktopViewport()) {
        body.classList.add('simpeg-sidebar-auto');
        body.classList.remove('sidebar-open');
      } else {
        body.classList.remove('simpeg-sidebar-auto', 'simpeg-sidebar-expanded', 'simpeg-sidebar-pinned');
      }
    }

    function expandDesktopSidebar() {
      if (!isDesktopViewport()) return;
      window.clearTimeout(sidebarCollapseTimer);
      body.classList.add('simpeg-sidebar-expanded');
    }

    function collapseDesktopSidebar() {
      if (!isDesktopViewport() || body.classList.contains('simpeg-sidebar-pinned')) return;
      sidebarCollapseTimer = window.setTimeout(function () {
        body.classList.remove('simpeg-sidebar-expanded');
      }, 140);
    }

    function closeAllSidebarMenus() {
      var opened = sidebar.querySelectorAll('.simpeg-side-item.menu-open');
      opened.forEach(function (item) {
        item.classList.remove('menu-open');
        var toggle = item.querySelector('[data-simpeg-submenu-toggle]');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }

    parentMenuLinks.forEach(function (link) {
      var item = link.closest('.simpeg-side-item');
      link.setAttribute('aria-expanded', item && item.classList.contains('menu-open') ? 'true' : 'false');
    });

    sidebarToggles.forEach(function (toggle) {
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (isDesktopViewport()) {
          var isPinned = body.classList.toggle('simpeg-sidebar-pinned');
          body.classList.toggle('simpeg-sidebar-expanded', isPinned);
        } else {
          body.classList.toggle('sidebar-open');
        }
      });
    });

    sidebarClosers.forEach(function (closer) {
      closer.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        body.classList.remove('sidebar-open');
      });
    });

    sidebar.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-simpeg-submenu-toggle]');
      if (!trigger || !sidebar.contains(trigger)) return;

      var item = trigger.closest('.simpeg-side-item');
      var isOpen = item && item.classList.contains('menu-open');

      event.preventDefault();
      event.stopPropagation();
      expandDesktopSidebar();
      closeAllSidebarMenus();
      if (item && !isOpen) {
        item.classList.add('menu-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });

    navigableSidebarLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        if (!isDesktopViewport()) {
          body.classList.remove('sidebar-open');
        }
      });
    });

    sidebar.addEventListener('mouseenter', expandDesktopSidebar);
    sidebar.addEventListener('mouseleave', collapseDesktopSidebar);
    sidebar.addEventListener('focusin', expandDesktopSidebar);
    sidebar.addEventListener('focusout', function (event) {
      if (!sidebar.contains(event.relatedTarget)) {
        collapseDesktopSidebar();
      }
    });

    document.addEventListener('click', function (event) {
      if (isDesktopViewport()) return;
      if (!body.classList.contains('sidebar-open')) return;

      var clickedInsideSidebar = event.target.closest('.simpeg-sidebar-panel');
      var clickedToggle = event.target.closest('[data-simpeg-sidebar-toggle]');
      if (!clickedInsideSidebar && !clickedToggle) {
        body.classList.remove('sidebar-open');
      }
    });

    window.addEventListener('resize', function () {
      setAutoSidebarMode();
    });

    setAutoSidebarMode();
  }

  window.SimpegUI = window.SimpegUI || {
    showLoader: showLoader,
    hideLoader: hideLoader
  };

  onReady(function () {
    hideLoader();
    initSidebarMenu();

    var body = document.body;
    body.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (!link) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;
      if (
        link.getAttribute('href') === '#' ||
        link.getAttribute('href') === '' ||
        link.getAttribute('href').charAt(0) === '#' ||
        link.getAttribute('data-bs-toggle') === 'pill' ||
        link.getAttribute('data-bs-toggle') === 'tab' ||
        link.getAttribute('data-toggle') === 'pill' ||
        link.getAttribute('data-toggle') === 'tab'
      ) return;
      if (link.dataset.noLoading === 'true') return;

      var href = link.getAttribute('href');
      if (href && href.indexOf('javascript:') === 0) return;
      showLoader('Memuat halaman...');
    });

    body.addEventListener('submit', function (event) {
      var form = event.target;
      if (!form || form.dataset.noLoading === 'true') return;

      window.setTimeout(function () {
        var invalid = false;
        if (typeof form.checkValidity === 'function') {
          invalid = !form.checkValidity();
        }

        if (event.defaultPrevented || invalid) {
          hideLoader();
          return;
        }

        showLoader('Menyimpan data...');
      }, 0);
    }, true);

    if (
      window.jQuery &&
      window.jQuery.fn &&
      window.jQuery.fn.modal &&
      window.jQuery.fn.modal.Constructor &&
      window.jQuery.fn.modal.Constructor.prototype &&
      window.jQuery.fn.modal.Constructor.prototype._config
    ) {
      window.jQuery.fn.modal.Constructor.prototype._config.focus = true;
    }

    if (window.jQuery && window.jQuery.fn.select2) {
      window.jQuery('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
      });
    }

    if (window.jQuery && window.jQuery(document)) {
      window.jQuery(document).on('processing.dt', function (event, settings, processing) {
        if (processing) {
          showLoader('Memuat data...');
        } else {
          hideLoader();
        }
      });

      window.jQuery(document).ajaxStart(function () {
        showLoader('Memuat data...');
      });

      window.jQuery(document).ajaxStop(function () {
        hideLoader();
      });
    }
  });

  window.addEventListener('pageshow', hideLoader);
  window.addEventListener('load', hideLoader);
})();
