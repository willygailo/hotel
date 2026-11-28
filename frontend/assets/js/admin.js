/* ============================================================
   MARVELOUS HOTEL — Admin Dedicated JavaScript
   assets/js/admin.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Confirm Delete / Destructive Actions
  const deleteLinks = document.querySelectorAll('.action-delete, [data-confirm]');
  deleteLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const msg = link.getAttribute('data-confirm') || 'Are you sure you want to delete this record?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // 2. Universal Live Table Search (Cached + Debounced for 0ms workstation typing lag)
  const searchInputs = document.querySelectorAll('[data-table-search], #adminTableSearch');
  searchInputs.forEach(input => {
    const targetSelector = input.getAttribute('data-table-target') || '.table, .admin-table';
    let debounceTimer = null;
    const runFilter = () => {
      const term = (input.value || '').toLowerCase().trim();
      const rows = document.querySelectorAll(`${targetSelector} tbody tr`);
      rows.forEach(row => {
        if (row.hasAttribute('data-empty-row')) return;
        if (row.dataset.searchCache === undefined) {
          row.dataset.searchCache = (row.textContent || '').toLowerCase().replace(/\s+/g, ' ');
        }
        row.style.display = (term === '' || row.dataset.searchCache.includes(term)) ? '' : 'none';
      });
    };
    input.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => window.requestAnimationFrame(runFilter), 90);
    });
  });

  // 3. Quick Stats animation
  const statValues = document.querySelectorAll('.admin-stat-value');
  statValues.forEach(val => {
    val.style.transition = 'transform 0.2s ease';
    val.addEventListener('mouseenter', () => val.style.transform = 'scale(1.05)');
    val.addEventListener('mouseleave', () => val.style.transform = 'scale(1)');
  });

  // 4. Close visible Admin modals on Backdrop click or Escape key
  document.querySelectorAll('.admin-modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    // Workstation shortcut: press '/' to focus search input
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      const tag = (document.activeElement?.tagName || '').toLowerCase();
      if (tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
        const firstSearch = document.querySelector('[data-table-search], #adminTableSearch, input[name="q"]');
        if (firstSearch) {
          e.preventDefault();
          firstSearch.focus();
          firstSearch.select();
        }
      }
      return;
    }
    if (e.key === 'Escape') {
      document.querySelectorAll('.admin-modal-overlay, .timeline-modal').forEach(modal => {
        if (modal.style.display && modal.style.display !== 'none') {
          modal.style.display = 'none';
        }
      });
    }
  });
});

// 5. Audit Logs Tab Switcher (Staff Only / User Only / All Logs)
window.switchAuditTab = function(tabName, btn) {
  const panes = document.querySelectorAll('.audit-tab-pane');
  panes.forEach(pane => pane.style.display = 'none');

  const target = document.getElementById('audit-tab-' + tabName);
  if (target) {
    target.style.display = 'block';
  }

  const pills = document.querySelectorAll('.audit-tab-pills .tab-pill');
  pills.forEach(pill => pill.classList.remove('active'));

  if (btn) {
    btn.classList.add('active');
  }
};

// 6. Shared Masked Temporary Credential Toggle & Copy Helpers (Users & Staff Tables)
window.toggleTempCredential = function(btn) {
  const wrap = btn.closest('.cred-pill-wrap');
  if (!wrap) return;
  const valEl = wrap.querySelector('.cred-value');
  if (!valEl) return;

  const isRevealed = wrap.dataset.revealed === '1';
  if (isRevealed) {
    valEl.textContent = '••••••••';
    wrap.dataset.revealed = '0';
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    btn.title = 'Show Password';
  } else {
    valEl.textContent = wrap.dataset.secret || '';
    wrap.dataset.revealed = '1';
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    btn.title = 'Hide Password';
  }
};

window.copyTempCredential = function(btn) {
  const wrap = btn.closest('.cred-pill-wrap');
  if (!wrap || !wrap.dataset.secret) return;
  const doAck = () => {
    const prev = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color:#4ade80;"></i>';
    setTimeout(() => { btn.innerHTML = prev; }, 1600);
  };
  if (typeof copyToClipboard === 'function') {
    copyToClipboard(wrap.dataset.secret, doAck);
  } else if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(wrap.dataset.secret).then(doAck).catch(() => {});
  }
};
