<?php
// ============================================================
// MARVELOUS HOTEL — Shared Portal Sidebar
// iisang component para sa lahat ng admin/ user/ staff/ pages.
// Gamit sa page:
//   require_once __DIR__ . '/../includes/components/sidebar.php';
//   renderSidebar('admin');   // 'admin' | 'user' | 'staff'
//
// - Ang aktibong link ay awtomatikong batay sa filename ng page.
// - Ang badge ay lilitaw lang kung defined ang corresponding
//   variable sa page (hal. $pendingPayments sa dashboard).
// - Ang footer ay gumagamit ng session (currentUser()).
// ============================================================

if (!function_exists('renderSidebar')) {
    function renderSidebar(string $role): void
    {
        $me = function_exists('currentUser') ? currentUser() : null;
        $active = basename($_SERVER['SCRIPT_NAME'] ?? '');

        if ($role === 'admin') {
            $icon       = '👑';
            $iconStyle  = 'background:linear-gradient(135deg,var(--gold),#e94560);';
            $sub        = 'Full Admin Control';
            $footRole   = '(Super Admin)';
            $footStyle  = 'style="color:var(--gold);"';
            $footSub    = 'Full Access';

            $pendingResetsCount = 0;
            $pendingPaymentsCount = isset($GLOBALS['pendingPayments']) ? (int)$GLOBALS['pendingPayments'] : 0;
            $flaggedAiCount = isset($GLOBALS['flaggedAiLogs']) ? (int)$GLOBALS['flaggedAiLogs'] : 0;
            $latestReset = null;
            if (function_exists('dbRow')) {
                $pendingResetsCount = (int)(dbRow("SELECT COUNT(*) as c FROM password_reset_requests WHERE status = 'pending'")['c'] ?? 0);
                if (!isset($GLOBALS['pendingPayments'])) {
                    $pendingPaymentsCount = (int)(dbRow("SELECT COUNT(*) as c FROM payments WHERE status = 'pending'")['c'] ?? 0);
                }
                if (!isset($GLOBALS['flaggedAiLogs'])) {
                    $flaggedAiCount = (int)(dbRow("SELECT COUNT(*) as c FROM ai_logs WHERE flagged = 1")['c'] ?? 0);
                }
                $onResetsTab = ($active === 'users.php' && (($_GET['tab'] ?? '') === 'resets'));
                if ($pendingResetsCount > 0 && !$onResetsTab) {
                    $latestReset = dbRow("SELECT pr.*, u.first_name, u.last_name FROM password_reset_requests pr LEFT JOIN users u ON pr.user_id = u.id WHERE pr.status = 'pending' ORDER BY pr.id DESC LIMIT 1");
                }
            }

            $groups = [
                ['label' => 'General', 'items' => [
                    ['dashboard.php', 'fa-chart-line', 'Overview &amp; Analytics'],
                    ['rooms.php', 'fa-door-closed', 'Manage Rooms'],
                    ['bookings.php', 'fa-calendar-days', 'All Bookings'],
                    ['payments.php', 'fa-money-bill-wave', 'Verify Payments', '', $pendingPaymentsCount],
                ]],
                ['label' => 'Accounts &amp; People', 'items' => [
                    ['users.php', 'fa-users', 'User Accounts' . ($pendingResetsCount > 0 ? " <span class='badge' style='background:#f97316;color:#fff;font-size:0.68rem;padding:2px 6px;margin-left:6px;border-radius:10px;'>{$pendingResetsCount} Resets</span>" : '')],
                    ['staff.php', 'fa-id-badge', 'Staff Accounts'],
                ]],
                ['label' => 'AI &amp; Security', 'items' => [
                    ['ai-config.php', 'fa-robot', 'AI Config &amp; Logs', 'color:#7B5EA7;', $flaggedAiCount],
                    ['audit-logs.php', 'fa-shield-halved', 'Audit &amp; Activity Logs'],
                    ['settings.php', 'fa-sliders', 'System Settings'],
                ]],
            ];
            $logoutStyle = 'margin-top:20px;';
        } elseif ($role === 'staff') {
            $icon       = 'S';
            $iconStyle  = 'background:linear-gradient(135deg,var(--accent),var(--accent2));';
            $sub        = 'Staff Desk';
            $footRole   = 'Hotel Staff';
            $footStyle  = 'style="color:var(--accent);"';
            $footSub    = '';

            $pendingBookingsCount = isset($GLOBALS['pendingBookings']) ? (int)$GLOBALS['pendingBookings'] : 0;
            $pendingPaymentsCount = isset($GLOBALS['pendingPayments']) ? (int)$GLOBALS['pendingPayments'] : 0;
            if (function_exists('dbRow')) {
                if (!isset($GLOBALS['pendingBookings'])) {
                    $pendingBookingsCount = (int)(dbRow("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")['c'] ?? 0);
                }
                if (!isset($GLOBALS['pendingPayments'])) {
                    $pendingPaymentsCount = (int)(dbRow("SELECT COUNT(*) as c FROM payments WHERE status = 'pending'")['c'] ?? 0);
                }
            }

            $groups = [
                ['label' => 'Staff Operations', 'items' => [
                    ['dashboard.php', 'fa-bell-concierge', 'Frontdesk Dashboard'],
                    ['dashboard.php#pending-bookings', 'fa-list-check', 'Accept Bookings', '', $pendingBookingsCount],
                    ['payments.php', 'fa-money-bill-wave', 'Verify Payments', '', $pendingPaymentsCount],
                    ['check-in.php', 'fa-door-open', 'Check-in &amp; Check-out'],
                    ['rooms-status.php', 'fa-bed', 'Room Status'],
                ]],
                ['label' => 'Account', 'items' => [
                    ['account.php', 'fa-user-lock', 'Account &amp; Password'],
                ]],
            ];
            $logoutStyle = 'margin-top:10px;';
        } else { // user
            $icon       = 'M';
            $iconStyle  = '';
            $sub        = 'User Portal';
            $footRole   = 'User Account';
            $footStyle  = '';
            $footSub    = '';
            $groups = [
                ['label' => 'Main Menu', 'items' => [
                    ['dashboard.php', 'fa-gauge-high', 'Dashboard'],
                    ['../rooms.php', 'fa-door-open', 'Browse Rooms'],
                    ['my-bookings.php', 'fa-book-bookmark', 'My Bookings', '', 'activeBookings'],
                    ['payment-history.php', 'fa-receipt', 'Payment History'],
                    ['ai-recommend.php', 'fa-wand-magic-sparkles', 'AI Recommendation', 'color:#7B5EA7;'],
                    ['reviews.php', 'fa-star', 'My Reviews'],
                ]],
                ['label' => 'Hotel Website', 'items' => [
                    ['../index.php', 'fa-hotel', 'Main Hotel Page'],
                ]],
                ['label' => 'Account', 'items' => [
                    ['profile.php', 'fa-user-gear', 'Profile Settings'],
                ]],
            ];
            $logoutStyle = '';
        }
        ?>
  <aside class="sidebar">
    <div class="sidebar-header">
      <a href="<?= h((defined('USER_SITE_URL') ? USER_SITE_URL : '..') . '/index.php') ?>" class="nav-logo">
        <div class="nav-logo-icon"<?= $iconStyle !== '' ? ' style="' . $iconStyle . '"' : '' ?>><?= $icon ?></div>
        <div class="nav-logo-text">
          <div class="nav-logo-title">Marvelous</div>
          <div class="nav-logo-sub"><?= $sub ?></div>
        </div>
      </a>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($groups as $group): ?>
        <div class="sidebar-nav-label"><?= $group['label'] ?></div>
        <?php foreach ($group['items'] as $rawItem): ?>
          <?php
            [$href, $iconClass, $label, $extraStyle, $badgeVar] = array_pad($rawItem, 5, '');
            $badgeCount = is_int($badgeVar) ? $badgeVar : ($badgeVar !== '' ? (int)($GLOBALS[$badgeVar] ?? 0) : 0);
          ?>
          <a href="<?= $href ?>" class="sidebar-link<?= basename($href) === $active ? ' active' : '' ?>">
            <i class="fa-solid <?= $iconClass ?> sidebar-icon"<?= $extraStyle !== '' ? ' style="' . $extraStyle . '"' : '' ?>></i> <?= $label ?>
            <?php if ($badgeCount > 0): ?>
              <span class="badge badge-warning"><?= $badgeCount ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <?php if ($role === 'staff'): ?>
        <div class="sidebar-nav-label">Limited Access</div>
        <div style="padding:10px 14px;font-size:0.75rem;color:var(--text-muted);background:rgba(255,255,255,0.02);border-radius:8px;margin:4px 8px;">
          <i class="fa-solid fa-lock" style="color:var(--gold);margin-right:4px;"></i> User account management &amp; room pricing are restricted to Super Admin.
        </div>
      <?php endif; ?>

      <a href="../auth/logout.php" class="sidebar-link danger"<?= $logoutStyle !== '' ? ' style="' . $logoutStyle . '"' : '' ?>>
        <i class="fa-solid fa-right-from-bracket sidebar-icon"></i> Logout
      </a>
    </nav>

    <?php if ($me): ?>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <img src="<?= h(getUserAvatarUrl($me['avatar'] ?? null)) ?>" decoding="async" style="object-fit:cover;border-radius:50%;" onerror="this.src='../frontend/assets/images/avatars/default-avatar.png'">
        <div style="overflow:hidden;">
          <div class="sidebar-user-name"><?= h($me['name']) ?><?= $role === 'admin' ? ' ' . $footRole : '' ?></div>
          <div class="sidebar-user-role" <?= $footStyle ?>><?= h($footSub !== '' ? $footSub : $footRole) ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <?php if ($role === 'admin' && !empty($latestReset)): ?>
    <div id="adminResetAlertBanner" data-reset-count="<?= (int)$pendingResetsCount ?>" style="position:fixed;bottom:24px;right:24px;background:#1e293b;border:1px solid #f97316;border-radius:12px;padding:16px 20px;box-shadow:0 12px 30px rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;gap:16px;max-width:460px;animation:slideInRight 0.3s ease;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(249,115,22,0.15);display:flex;align-items:center;justify-content:center;color:#f97316;font-size:1.2rem;flex-shrink:0;">
        <i class="fa-solid fa-key"></i>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;color:#fff;font-size:0.92rem;">Password Reset Request</div>
        <div style="font-size:0.8rem;color:#cbd5e1;margin-top:2px;">
          <strong><?= h(trim(($latestReset['first_name'] ?? '') . ' ' . ($latestReset['last_name'] ?? '')) ?: 'Guest') ?></strong> (<?= h($latestReset['email']) ?>) needs a new password.
        </div>
      </div>
      <a href="users.php?tab=resets" style="background:#f97316;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none;font-weight:700;font-size:0.8rem;white-space:nowrap;">
        Dispatch
      </a>
      <button type="button" onclick="(function(b){ try{ sessionStorage.setItem('mh_dismiss_reset_cnt', b.dataset.resetCount || '0'); }catch(e){} b.remove(); })(this.parentElement)" style="background:none;border:none;color:#94a3b8;font-size:1.1rem;cursor:pointer;">&times;</button>
    </div>
    <script>
      (function(){
        try {
          var b = document.getElementById('adminResetAlertBanner');
          if (b && sessionStorage.getItem('mh_dismiss_reset_cnt') === (b.dataset.resetCount || '')) {
            b.style.display = 'none';
          }
        } catch(e) {}
      })();
    </script>
  <?php endif; ?>
        <?php
    }
}
