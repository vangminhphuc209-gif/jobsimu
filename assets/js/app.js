/**
 * JobSimu - Accessible, responsive UI interactions
 */
(function () {
  'use strict';

  // --- Dropdown Management (Desktop Navigation & Account Menu) ---
  const navItemsWithDropdown = document.querySelectorAll('.nav-item.has-dropdown, .account-menu-item');

  function closeAllDropdowns(exceptItem) {
    navItemsWithDropdown.forEach(item => {
      if (item !== exceptItem) {
        item.classList.remove('open');
        const btn = item.querySelector('button[aria-haspopup="true"]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  navItemsWithDropdown.forEach(item => {
    const btn = item.querySelector('button[aria-haspopup="true"]');
    if (!btn) return;

    btn.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = item.classList.contains('open');
      closeAllDropdowns(isOpen ? null : item);
      item.classList.toggle('open', !isOpen);
      btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    });

    item.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        item.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }
    });
  });

  // Close dropdowns on click outside
  document.addEventListener('click', e => {
    if (!e.target.closest('.nav-item.has-dropdown') && !e.target.closest('.account-menu-item')) {
      closeAllDropdowns();
    }
  });

  // --- Mobile Drawer Navigation ---
  const mobileToggle = document.querySelector('.nav-mobile-toggle');
  const mobileDrawer = document.getElementById('mobileNavDrawer');
  const drawerCloseBtn = mobileDrawer?.querySelector('.mobile-drawer-close');

  function openMobileDrawer() {
    if (!mobileDrawer) return;
    mobileDrawer.classList.add('open');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    mobileToggle?.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    drawerCloseBtn?.focus();
  }

  function closeMobileDrawer() {
    if (!mobileDrawer) return;
    mobileDrawer.classList.remove('open');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    mobileToggle?.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    mobileToggle?.focus();
  }

  mobileToggle?.addEventListener('click', () => {
    const isOpen = mobileDrawer?.classList.contains('open');
    if (isOpen) {
      closeMobileDrawer();
    } else {
      openMobileDrawer();
    }
  });

  drawerCloseBtn?.addEventListener('click', closeMobileDrawer);

  mobileDrawer?.addEventListener('click', e => {
    if (e.target === mobileDrawer) {
      closeMobileDrawer();
    }
  });

  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeAllDropdowns();
      if (mobileDrawer?.classList.contains('open')) {
        closeMobileDrawer();
      }
    }
  });

  // Auto close mobile drawer on resize to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 960 && mobileDrawer?.classList.contains('open')) {
      closeMobileDrawer();
    }
  });

  // --- Common Helpers & Existing Handlers ---
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  const search = document.querySelector('[data-career-search]');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.toLowerCase().trim();
      document.querySelectorAll('[data-career]').forEach(c => {
        c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  window.showToast = function (message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
  };

  document.querySelectorAll('[data-submit-loading]').forEach(form => {
    form.addEventListener('submit', () => {
      document.getElementById('loadingOverlay')?.classList.add('active');
      form.querySelectorAll('button').forEach(b => (b.disabled = true));
    });
  });
})();
