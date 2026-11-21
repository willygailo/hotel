<?php
// ============================================================
// MARVELOUS HOTEL — Centralized Three-Dot (⋮) Navigation Drawer
// includes/components/central-nav.php
// ============================================================

if (!function_exists('renderCentralNav')) {
    function renderCentralNav(string $activePage = ''): void {
        $uNav = function_exists('isLoggedIn') && isLoggedIn() ? currentUser() : null;
        $kycNav = ($uNav && function_exists('isUser') && isUser()) ? getUserKycStatus($uNav['id']) : null;
        $navProfileUrl = ($uNav && function_exists('isUser') && isUser()) 
            ? SITE_URL . '/user/profile.php' 
            : (function_exists('isAdmin') && isAdmin() ? SITE_URL . '/admin/settings.php' : SITE_URL . '/staff/dashboard.php');
        $navSettingsUrl = ($uNav && function_exists('isUser') && isUser()) 
            ? SITE_URL . '/user/profile.php#security' 
            : (function_exists('isAdmin') && isAdmin() ? SITE_URL . '/admin/settings.php' : SITE_URL . '/staff/dashboard.php');
        $navPrefUrl = ($uNav && function_exists('isUser') && isUser()) 
            ? SITE_URL . '/user/ai-recommend.php' 
            : SITE_URL . '/rooms.php';
        ?>
<!-- ======================== CENTRALIZED NAVIGATION (THREE-DOT MENU) ======================== -->
<div class="central-nav-overlay" id="centralNavOverlay" onclick="closeCentralNav()"></div>
<div class="central-nav" id="centralNav" aria-label="Centralized Navigation Menu">
  <div class="central-nav-header">
    <a href="<?= SITE_URL ?>/index.php" class="central-nav-brand">
      <div class="central-nav-brand-icon">M</div>
      <div>
        <div class="central-nav-brand-title">Marvelous</div>
        <div class="central-nav-brand-sub">Hotel &amp; Dormitory</div>
      </div>
    </a>
    <button class="central-nav-close" id="centralNavClose" onclick="closeCentralNav()" aria-label="Close Navigation Menu">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <?php if ($uNav): ?>
    <a href="<?= $navProfileUrl ?>" class="central-nav-user-card" title="Open My Profile">
      <img src="<?= h(getUserAvatarUrl($uNav['avatar'])) ?>" alt="<?= h($uNav['name']) ?>" class="central-nav-user-avatar" decoding="async" onerror="this.src='<?= SITE_URL ?>/frontend/assets/images/avatars/default-avatar.png'">
      <div class="central-nav-user-info">
        <div class="central-nav-user-name"><?= h($uNav['name']) ?></div>
        <div class="central-nav-user-meta">
          <span class="central-nav-user-badge role"><?= h(ucfirst($uNav['role'])) ?></span>
          <?php if (!empty($kycNav['is_verified'])): ?>
            <span class="central-nav-user-badge verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
          <?php endif; ?>
        </div>
      </div>
      <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);font-size:0.8rem;"></i>
    </a>
  <?php endif; ?>

  <nav class="central-nav-body">
    <div class="central-nav-group-label">Navigation</div>
    <a href="<?= SITE_URL ?>/index.php" class="central-nav-item<?= $activePage === 'home' ? ' active' : '' ?>">
      <i class="fa-solid fa-house"></i>
      <span>Home</span>
    </a>
    <a href="<?= SITE_URL ?>/rooms.php" class="central-nav-item<?= $activePage === 'rooms' ? ' active' : '' ?>">
      <i class="fa-solid fa-bed"></i>
      <span>Rooms &amp; Dormitory</span>
    </a>
    <a href="<?= SITE_URL ?>/about.php" class="central-nav-item<?= $activePage === 'about' ? ' active' : '' ?>">
      <i class="fa-solid fa-circle-info"></i>
      <span>About Us</span>
    </a>
    <a href="<?= SITE_URL ?>/contact.php" class="central-nav-item<?= $activePage === 'contact' ? ' active' : '' ?>">
      <i class="fa-solid fa-phone-volume"></i>
      <span>Contact Us</span>
    </a>

    <div class="central-nav-group-label" style="margin-top:8px;">Account &amp; Settings</div>
    <?php if ($uNav): ?>
      <a href="<?= $navProfileUrl ?>" class="central-nav-item<?= $activePage === 'profile' ? ' active' : '' ?>">
        <i class="fa-solid fa-user-circle"></i>
        <span>My Profile</span>
      </a>
      <a href="<?= SITE_URL ?>/<?= currentRole() ?>/dashboard.php" class="central-nav-item<?= $activePage === 'dashboard' ? ' active' : '' ?>">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Dashboard</span>
      </a>
      <?php if (isUser()): ?>
        <a href="<?= SITE_URL ?>/user/my-bookings.php" class="central-nav-item<?= $activePage === 'bookings' ? ' active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i>
          <span>My Bookings</span>
        </a>
      <?php endif; ?>
      <a href="<?= $navSettingsUrl ?>" class="central-nav-item<?= $activePage === 'settings' ? ' active' : '' ?>">
        <i class="fa-solid fa-gear"></i>
        <span>Account Settings</span>
      </a>
      <a href="<?= $navPrefUrl ?>" class="central-nav-item<?= $activePage === 'preferences' ? ' active' : '' ?>">
        <i class="fa-solid fa-sliders"></i>
        <span>Preferences</span>
      </a>

      <div class="central-nav-divider"></div>
      <a href="<?= SITE_URL ?>/auth/logout.php" class="central-nav-item danger">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
      </a>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/auth/login.php" class="central-nav-item">
        <i class="fa-solid fa-arrow-right-to-bracket"></i>
        <span>Log In</span>
      </a>
      <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-gold btn-sm" style="margin-top:10px;justify-content:center;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-user-plus"></i>
        <span>Register Free</span>
      </a>
    <?php endif; ?>
  </nav>
</div>
        <?php
    }
}

