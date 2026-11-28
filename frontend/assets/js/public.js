/* ============================================================
   MARVELOUS HOTEL — Public Site JavaScript
   assets/js/public.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Sticky Navbar styling (rAF-throttled for 60fps Android scrolling)
  const navbar = document.getElementById('navbar');
  if (navbar) {
    let scrollTicking = false;
    const syncNavbarScroll = () => {
      navbar.classList.toggle('scrolled', window.scrollY > 30);
      scrollTicking = false;
    };
    syncNavbarScroll();
    window.addEventListener('scroll', () => {
      if (!scrollTicking) {
        scrollTicking = true;
        window.requestAnimationFrame(syncNavbarScroll);
      }
    }, { passive: true });
  }

  // 2. Centralized Navigation Toggle (Profile Badge & Guest Hamburger)
  const menuBtn = document.getElementById('navMenuBtn') || document.getElementById('navHamburger');
  const centralNav = document.getElementById('centralNav') || document.getElementById('mobileNav');
  const overlay = document.getElementById('centralNavOverlay') || document.getElementById('mobileNavOverlay');
  const closeBtn = document.getElementById('centralNavClose');
  const profileBadges = document.querySelectorAll('.nav-profile-badge');

  window.openCentralNav = function() {
    if (centralNav) {
      centralNav.classList.add('open');
      centralNav.setAttribute('aria-hidden', 'false');
    }
    if (overlay) {
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
    }
    if (menuBtn) {
      menuBtn.classList.add('active');
      menuBtn.setAttribute('aria-expanded', 'true');
    }
    profileBadges.forEach(b => {
      b.classList.add('active');
      b.setAttribute('aria-expanded', 'true');
    });
    document.body.style.overflow = 'hidden';
  };

  window.closeCentralNav = function() {
    if (centralNav) {
      centralNav.classList.remove('open');
      centralNav.setAttribute('aria-hidden', 'true');
    }
    if (overlay) {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
    }
    if (menuBtn) {
      menuBtn.classList.remove('active');
      menuBtn.setAttribute('aria-expanded', 'false');
    }
    profileBadges.forEach(b => {
      b.classList.remove('active');
      b.setAttribute('aria-expanded', 'false');
    });
    document.body.style.overflow = '';
  };

  window.toggleCentralNav = function() {
    if (centralNav && centralNav.classList.contains('open')) {
      window.closeCentralNav();
    } else {
      window.openCentralNav();
    }
  };

  // Legacy compatibility
  window.closeMobileNav = window.closeCentralNav;

  if (menuBtn) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      window.toggleCentralNav();
    });
  }

  profileBadges.forEach(badge => {
    badge.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.toggleCentralNav();
    });
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', window.closeCentralNav);
  }

  if (overlay) {
    overlay.addEventListener('click', window.closeCentralNav);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      window.closeCentralNav();
    }
  });

  // 3. Set Default Search Dates (using local calendar date to avoid UTC-8/+8 day shift)
  function formatLocalYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  const searchCheckIn = document.getElementById('searchCheckIn');
  const searchCheckOut = document.getElementById('searchCheckOut');
  if (searchCheckIn && searchCheckOut) {
    const now = new Date();
    const tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
    if (!searchCheckIn.value) searchCheckIn.value = formatLocalYmd(now);
    if (!searchCheckOut.value) searchCheckOut.value = formatLocalYmd(tomorrow);

    searchCheckIn.addEventListener('change', () => {
      if (!searchCheckIn.value) return;
      const parts = searchCheckIn.value.split('-').map(Number);
      if (parts.length !== 3) return;
      const nextDay = new Date(parts[0], parts[1] - 1, parts[2] + 1);
      const minOut = formatLocalYmd(nextDay);
      searchCheckOut.min = minOut;
      if (!searchCheckOut.value || searchCheckOut.value <= searchCheckIn.value) {
        searchCheckOut.value = minOut;
      }
    });
  }
});

// Quick search action
function performSearch() {
  const checkIn = document.getElementById('searchCheckIn')?.value || '';
  const checkOut = document.getElementById('searchCheckOut')?.value || '';
  const guests = document.getElementById('searchGuests')?.value || '';
  const type = document.getElementById('searchType')?.value || '';
  
  let params = new URLSearchParams();
  if (checkIn) params.append('check_in', checkIn);
  if (checkOut) params.append('check_out', checkOut);
  if (guests) params.append('guests', guests.charAt(0));
  if (type && type !== 'All Types') params.append('type', type);
  
  window.location.href = 'rooms.php?' + params.toString();
}
