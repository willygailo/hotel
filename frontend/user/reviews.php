<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();
$preselectedBookingId = (int)($_GET['booking_id'] ?? 0);

// Eligible bookings for review (confirmed, checked_in, or checked_out bookings without existing review)
$eligibleBookings = dbRows("
    SELECT b.*, r.name as room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN reviews rev ON b.id = rev.booking_id 
    WHERE b.user_id = ? AND b.status IN ('confirmed', 'checked_in', 'checked_out') AND rev.id IS NULL
    ORDER BY b.id DESC
", [$userId]);

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bId     = (int)($_POST['booking_id'] ?? 0);
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $title   = trim($_POST['title'] ?? '');

        $res = submitUserReview($userId, $bId, $rating, $comment, $title, 'User Module');
        setFlash($res['flash_type'], $res['message']);
        if ($res['ok']) {
            header("Location: reviews.php");
            exit;
        }
    }
}

// User past reviews
$myReviews = dbRows("
    SELECT rev.*, r.name as room_name 
    FROM reviews rev 
    LEFT JOIN rooms r ON rev.room_id = r.id 
    WHERE rev.user_id = ? 
    ORDER BY rev.id DESC
", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Reviews — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(true); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/user.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Customer Feedback and Reviews</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Leave a Review</h1>
          <div class="page-header-sub">Share your stay experience to help other customers.</div>
        </div>
      </div>

      <!-- Review Form -->
      <div class="glass-card" style="padding:24px;margin-bottom:28px;">
        <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin-bottom:14px;"><i class="fa-solid fa-pen"></i> Write Your Review</h3>
        <form action="" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="submit_review" value="1">
          <div class="form-group">
            <label class="form-label">Select Your Stay / Room</label>
            <select name="booking_id" class="form-control">
              <?php if (!empty($eligibleBookings)): ?>
                <?php foreach ($eligibleBookings as $el): ?>
                  <option value="<?= $el['id'] ?>" <?= ((int)$el['id'] === $preselectedBookingId) ? 'selected' : '' ?>>Booking #<?= h($el['booking_code']) ?> - <?= h($el['room_name']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
              <option value="0" <?= ($preselectedBookingId <= 0 && empty($eligibleBookings)) ? 'selected' : '' ?>>General Stay &amp; Customer Experience</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Rating (Stars)</label>
            <select name="rating" class="form-control" required>
              <option value="5" selected>⭐⭐⭐⭐⭐ (5 Stars - Excellent)</option>
              <option value="4">⭐⭐⭐⭐ (4 Stars - Good)</option>
              <option value="3">⭐⭐⭐ (3 Stars - Average)</option>
              <option value="2">⭐⭐ (2 Stars - Needs Improvement)</option>
              <option value="1">⭐ (1 Star - Not Satisfied)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Your Comment and Feedback</label>
            <textarea name="comment" class="form-control" rows="4" placeholder="Tell us about your experience with our rooms, staff, and amenities..." required></textarea>
          </div>
          <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> Submit &amp; Post Review</button>
        </form>
      </div>

      <!-- Previous Reviews List -->
      <div class="card">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-comment-dots" style="color:var(--gold);margin-right:8px;"></i> My Previous Reviews</h2></div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap desktop-bookings-table">
            <table class="table">
              <thead>
                <tr>
                  <th>Room</th>
                  <th>Rating</th>
                  <th>Comment</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($myReviews)): ?>
                  <tr><td colspan="4" class="text-center" style="padding:30px;color:var(--text-muted);">You have not written any reviews yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($myReviews as $rv): ?>
                    <tr>
                      <td><strong><?= h($rv['room_name'] ?: 'General Stay') ?></strong></td>
                      <td><?= starRating($rv['rating']) ?></td>
                      <td style="color:var(--text-muted);font-style:italic;">"<?= h($rv['comment']) ?>"</td>
                      <td><?= formatDate($rv['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mobile-bookings-list">
            <?php if (empty($myReviews)): ?>
              <div class="text-center" style="padding:30px 15px;color:var(--text-muted);">
                You have not written any reviews yet.
              </div>
            <?php else: ?>
              <?php foreach ($myReviews as $rv): ?>
                <div class="booking-mobile-card">
                  <div class="bmc-header">
                    <div class="bmc-code" style="font-size:0.95rem;"><?= h($rv['room_name'] ?: 'General Stay') ?></div>
                    <div><?= starRating($rv['rating']) ?></div>
                  </div>
                  <p style="font-size:0.86rem;color:var(--text-muted);font-style:italic;line-height:1.55;margin-bottom:8px;">"<?= h($rv['comment']) ?>"</p>
                  <div style="font-size:0.75rem;color:var(--text-muted);"><?= formatDate($rv['created_at']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/user.js')) ?>"></script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
