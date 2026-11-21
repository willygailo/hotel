/* ============================================================
   MARVELOUS HOTEL — User Dedicated JavaScript
   assets/js/user.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Dynamic Booking Price Calculation
  const checkInInput = document.querySelector('input[name="check_in"]');
  const checkOutInput = document.querySelector('input[name="check_out"]');
  const roomSelect = document.querySelector('select[name="room_id"]');
  const summaryBox = document.getElementById('userBookingSummary');
  const nightsDisplay = document.getElementById('calcNights');
  const totalDisplay = document.getElementById('calcTotal');

  function calculateBooking() {
    if (!checkInInput || !checkOutInput || !roomSelect || !summaryBox) return;

    const inDate = new Date(checkInInput.value);
    const outDate = new Date(checkOutInput.value);
    const selectedOpt = roomSelect.options[roomSelect.selectedIndex];
    const price = parseFloat(selectedOpt?.getAttribute('data-price') || 0);

    if (inDate && outDate && outDate > inDate && price > 0) {
      const diffTime = Math.abs(outDate - inDate);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      const total = diffDays * price;

      if (nightsDisplay) nightsDisplay.textContent = `${diffDays} nights`;
      if (totalDisplay) totalDisplay.textContent = `₱${total.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
      summaryBox.style.display = 'block';
    } else if (summaryBox) {
      summaryBox.style.display = 'none';
    }
  }

  if (checkInInput) checkInInput.addEventListener('change', calculateBooking);
  if (checkOutInput) checkOutInput.addEventListener('change', calculateBooking);
  if (roomSelect) roomSelect.addEventListener('change', calculateBooking);

  // 2. Payment Proof Image Preview
  const proofInput = document.querySelector('input[type="file"][name="proof"]');
  const previewContainer = document.getElementById('proofPreview');
  const previewImg = document.getElementById('proofImg');

  if (proofInput && previewContainer && previewImg) {
    proofInput.addEventListener('change', function () {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          previewImg.src = e.target.result;
          previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        previewContainer.style.display = 'none';
      }
    });
  }

  // 3. Interactive Star Rating Selector
  const starSelect = document.getElementById('starRatingSelect');
  const starInput = document.getElementById('ratingInput');
  if (starSelect && starInput) {
    const stars = starSelect.querySelectorAll('.star-icon');
    stars.forEach(star => {
      star.addEventListener('click', () => {
        const val = parseInt(star.getAttribute('data-value'), 10);
        starInput.value = val;
        stars.forEach(s => {
          const sVal = parseInt(s.getAttribute('data-value'), 10);
          s.style.opacity = sVal <= val ? '1' : '0.3';
        });
      });
    });
  }
});
