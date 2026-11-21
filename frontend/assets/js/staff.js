/* ============================================================
   MARVELOUS HOTEL — Staff / Frontdesk Dedicated JavaScript
   assets/js/staff.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Live Guest Search in Staff Queue
  const guestSearch = document.getElementById('staffGuestSearch');
  if (guestSearch) {
    guestSearch.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase();
      const cards = document.querySelectorAll('.staff-guest-row, .table tbody tr');
      cards.forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(term) ? '' : 'none';
      });
    });
  }

  // 2. Room Status Filter (All, Available, Occupied, Maintenance)
  const filterBtns = document.querySelectorAll('[data-status-filter]');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.getAttribute('data-status-filter');
      const cards = document.querySelectorAll('.room-status-card');
      cards.forEach(card => {
        if (filter === 'all' || card.classList.contains(filter)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // 3. Confirm Check-in / Check-out Prompts
  const actionForms = document.querySelectorAll('.staff-action-form');
  actionForms.forEach(form => {
    form.addEventListener('submit', (e) => {
      const actionType = form.querySelector('[name="action_type"]')?.value || 'action';
      if (!confirm(`Confirm ${actionType.toUpperCase()} for this booking?`)) {
        e.preventDefault();
      }
    });
  });
});
