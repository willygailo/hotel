/* ============================================================
   MARVELOUS HOTEL — Staff / Frontdesk Dedicated JavaScript
   assets/js/staff.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Universal Live Table Search ([data-table-search] -> data-table-target)
  const searchInputs = document.querySelectorAll('[data-table-search]');
  searchInputs.forEach(input => {
    const targetSelector = input.getAttribute('data-table-target') || '.table';
    const applyFilter = () => {
      const term = (input.value || '').toLowerCase().trim();
      const rows = document.querySelectorAll(`${targetSelector} tbody tr`);
      let visibleCount = 0;
      let dataRowsCount = 0;

      rows.forEach(row => {
        if (row.hasAttribute('data-empty-row') || row.hasAttribute('data-no-match-row')) return;
        dataRowsCount++;
        const text = (row.innerText || '').toLowerCase();
        const show = term === '' || text.includes(term);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });

      const emptyMatchRow = document.querySelector(`${targetSelector} tbody tr[data-no-match-row]`);
      if (emptyMatchRow && dataRowsCount > 0) {
        emptyMatchRow.style.display = (visibleCount === 0) ? '' : 'none';
      }
    };

    input.addEventListener('input', applyFilter);
  });

  // 2. Live Room Status Filter + Search (rooms-status.php)
  const filterBtns = document.querySelectorAll('[data-status-filter]');
  const roomSearchInput = document.getElementById('roomSearchInput');
  let activeStatusFilter = 'all';

  const applyRoomCardFilters = () => {
    const term = roomSearchInput ? (roomSearchInput.value || '').toLowerCase().trim() : '';
    const cards = document.querySelectorAll('.room-status-card');
    let visible = 0;

    cards.forEach(card => {
      const matchesStatus = (activeStatusFilter === 'all' || card.classList.contains(activeStatusFilter));
      const matchesSearch = (term === '' || (card.innerText || '').toLowerCase().includes(term));
      const show = matchesStatus && matchesSearch;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const emptyNotice = document.getElementById('roomsEmptyNotice');
    if (emptyNotice) {
      emptyNotice.style.display = visible === 0 ? 'block' : 'none';
    }
  };

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeStatusFilter = btn.getAttribute('data-status-filter') || 'all';
      applyRoomCardFilters();
    });
  });

  if (roomSearchInput) {
    roomSearchInput.addEventListener('input', applyRoomCardFilters);
  }

  // 3. Check-in / Check-out Guarded Form Confirmation
  document.querySelectorAll('form.staff-check-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      const dirtyRoom = form.getAttribute('data-dirty-room') === '1';
      const roomLabel = form.getAttribute('data-room-label') || 'this room';
      const codeLabel = form.getAttribute('data-booking-code') || '';
      const submitter = e.submitter;
      const actType = submitter?.value || form.querySelector('[name="action_type"]')?.value || '';

      if (actType === 'check_in') {
        if (dirtyRoom && !confirm(`Room ${roomLabel} is currently marked Needs Cleaning (Dirty). Continue checking in #${codeLabel}?`)) {
          e.preventDefault();
          return;
        }
        const unpaidOverride = form.querySelector('[name="confirm_unpaid_checkin"]');
        if (unpaidOverride && !unpaidOverride.checked) {
          e.preventDefault();
          alert(`Payment for Booking #${codeLabel} is not verified yet. Please verify the payment first or tick "Desk Override (Unpaid)" if payment is being settled at the frontdesk.`);
          unpaidOverride.focus();
          return;
        }
      } else if (actType === 'check_out') {
        if (!confirm(`Confirm Check-out for Booking #${codeLabel} (Room ${roomLabel})?`)) {
          e.preventDefault();
        }
      }
    });
  });

  // 4. Unified Backdrop & Escape Closing for Staff Modals
  document.querySelectorAll('.staff-modal-overlay, .inspector-modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.staff-modal-overlay, .inspector-modal').forEach(modal => {
        if (modal.style.display && modal.style.display !== 'none') {
          modal.style.display = 'none';
        }
      });
    }
  });
});

// Helper for Decline Booking Reason Modal (staff/dashboard.php)
window.openStaffDeclineModal = function(bookingId, bookingCode) {
  const modal = document.getElementById('staffDeclineModal');
  if (!modal) return;
  const idInput = document.getElementById('declineBookingId');
  const codeEl = document.getElementById('declineBookingCode');
  const reasonEl = document.getElementById('declineReasonInput');
  if (idInput) idInput.value = bookingId;
  if (codeEl) codeEl.textContent = '#' + bookingCode;
  if (reasonEl) reasonEl.value = '';
  modal.style.display = 'flex';
  setTimeout(() => { if (reasonEl) reasonEl.focus(); }, 60);
};

window.closeStaffDeclineModal = function() {
  const modal = document.getElementById('staffDeclineModal');
  if (modal) modal.style.display = 'none';
};

window.setDeclinePreset = function(text) {
  const reasonEl = document.getElementById('declineReasonInput');
  if (reasonEl) reasonEl.value = text;
};
