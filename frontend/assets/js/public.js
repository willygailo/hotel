/* ============================================================
   MARVELOUS HOTEL — Public Site JavaScript
   assets/js/public.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Sticky Navbar styling
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 30);
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
      centralNav.style.transform = 'translateX(0)';
    }
    if (overlay) {
      overlay.classList.add('open');
      overlay.style.display = 'block';
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
      centralNav.style.transform = 'translateX(100%)';
    }
    if (overlay) {
      overlay.classList.remove('open');
      overlay.style.display = 'none';
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
    if (centralNav && (centralNav.classList.contains('open') || centralNav.style.transform === 'translateX(0px)' || centralNav.style.transform === 'translateX(0)')) {
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

  // 3. Set Default Search Dates
  const searchCheckIn = document.getElementById('searchCheckIn');
  const searchCheckOut = document.getElementById('searchCheckOut');
  if (searchCheckIn && searchCheckOut) {
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    if (!searchCheckIn.value) searchCheckIn.value = today;
    if (!searchCheckOut.value) searchCheckOut.value = tomorrow;
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
