<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();

// Mark all as read
dbExec("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$userId]);

$notifs = dbRows("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Notification Center</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-header-title">Notifications and Alerts</h1>
          <div class="page-header-sub">Stay up to date on your reservation status and announcements.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
          <?php if (empty($notifs)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fa-solid fa-bell-slash"></i></div>
              <div class="empty-state-title">No new notifications</div>
              <div class="empty-state-desc">You are all caught up!</div>
            </div>
          <?php else: ?>
            <?php foreach ($notifs as $n): ?>
              <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;align-items:flex-start;gap:14px;">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);flex-shrink:0;">
                  <i class="fa-solid fa-bell"></i>
                </div>
                <div style="flex:1;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <strong style="color:#fff;font-size:0.95rem;"><?= h($n['title']) ?></strong>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?= formatDate($n['created_at'], 'M d, Y h:i A') ?></span>
                  </div>
                  <p style="font-size:0.85rem;color:var(--text-muted);line-height:1.5;"><?= h($n['message']) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
