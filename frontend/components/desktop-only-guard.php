<?php
// ============================================================
// MARVELOUS HOTEL — Laptop & Desktop PC Only Guard (Staff & Admin)
// frontend/components/desktop-only-guard.php
// Enforces workstation-only access (>= 1024px) for Staff and Admin portals.
// ============================================================

if (!function_exists('renderDesktopOnlyGuard')) {
    function renderDesktopOnlyGuard(string $portal = 'admin'): void
    {
        if (!empty($GLOBALS['mh_desktop_guard_rendered'])) {
            return;
        }
        $GLOBALS['mh_desktop_guard_rendered'] = true;

        $isStaff = ($portal === 'staff');
        $title = $isStaff
            ? 'Staff Frontdesk Portal — Laptop &amp; PC Only'
            : 'Admin Control Portal — Laptop &amp; PC Only';
        $badgeText = $isStaff ? 'Frontdesk Workstation' : 'Executive Workstation';
        $accentColor = $isStaff ? '#4E9AF1' : '#C9A84C';
        $accentBg = $isStaff ? 'rgba(78, 154, 241, 0.16)' : 'rgba(201, 168, 76, 0.16)';
        $accentBorder = $isStaff ? 'rgba(78, 154, 241, 0.35)' : 'rgba(201, 168, 76, 0.35)';
        $mainSiteUrl = (defined('USER_SITE_URL') ? USER_SITE_URL : SITE_URL) . '/index.php';
        ?>
<div class="desktop-only-guard" id="desktopOnlyGuard" role="dialog" aria-modal="true" aria-labelledby="desktopGuardTitle">
  <div class="desktop-only-guard-card">
    <div class="desktop-only-guard-badge" style="background:<?= $accentBg ?>;color:<?= $accentColor ?>;border-color:<?= $accentBorder ?>;">
      <i class="fa-solid fa-desktop"></i> <?= $badgeText ?>
    </div>
    <div class="desktop-only-guard-icon" style="background:<?= $accentBg ?>;color:<?= $accentColor ?>;border-color:<?= $accentBorder ?>;">
      <i class="fa-solid fa-laptop"></i>
    </div>
    <h1 id="desktopGuardTitle" class="desktop-only-guard-title"><?= $title ?></h1>
    <p class="desktop-only-guard-desc">
      Ang <strong><?= $isStaff ? 'Staff Frontdesk' : 'Admin Management' ?> Portal</strong> ay naka-optimize lamang para sa
      <strong>Laptop at Desktop PC</strong> (minimum <strong>1024px</strong> screen width) para sa mabilis, accurate, at error-free na pag-manage ng bookings, receipts, at records.
    </p>
    <div class="desktop-only-guard-meta">
      <div><i class="fa-solid fa-ruler-horizontal"></i> Current Screen: <strong id="desktopGuardViewport">--</strong></div>
      <div><i class="fa-solid fa-check-circle" style="color:#2ECC71;"></i> Required: <strong>1024px+ (Laptop / PC)</strong></div>
    </div>
    <div class="desktop-only-guard-actions">
      <a href="<?= h($mainSiteUrl) ?>" class="btn btn-gold btn-block">
        <i class="fa-solid fa-hotel"></i> Go to Main Hotel Website
      </a>
    </div>
    <p class="desktop-only-guard-hint">
      Kung nasa Laptop/PC ka naman, i-maximize ang browser window o bawasan ang browser zoom (Ctrl + 0).
    </p>
  </div>
</div>
        <?php
    }
}
