/* ============================================================
   MARVELOUS HOTEL — Admin Dedicated JavaScript
   assets/js/admin.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Confirm Delete Actions
  const deleteLinks = document.querySelectorAll('.action-delete, [data-confirm]');
  deleteLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const msg = link.getAttribute('data-confirm') || 'Are you sure you want to delete this record?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // 2. Realtime Table Search Filter
  const tableSearch = document.getElementById('adminTableSearch');
  if (tableSearch) {
    tableSearch.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('.admin-table tbody tr');
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    });
  }

  // 3. Quick Stats animation
  const statValues = document.querySelectorAll('.admin-stat-value');
  statValues.forEach(val => {
    val.style.transition = 'transform 0.2s ease';
    val.addEventListener('mouseenter', () => val.style.transform = 'scale(1.05)');
    val.addEventListener('mouseleave', () => val.style.transform = 'scale(1)');
  });
});

// 4. Audit Logs Tab Switcher (Staff Only / User Only / All Logs)
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

