<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

// Throttled housekeeping: cancel stale unpaid bookings & auto-release expired stays
throttleStaleBookingSweep(24, 15);

$adminId = currentUserId();
$admin = dbRow("SELECT * FROM users WHERE id = ?", [$adminId]);

// Full system aggregates (expose pendingPayments/flaggedAiLogs cleanly to shared sidebar)
$GLOBALS['pendingPayments'] = $pendingPayments = (int)(dbRow("SELECT COUNT(*) as cnt FROM payments WHERE status = 'pending'")['cnt'] ?? 0);
$GLOBALS['flaggedAiLogs'] = $flaggedAiLogs = (int)(dbRow("SELECT COUNT(*) as cnt FROM ai_logs WHERE flagged = 1")['cnt'] ?? 0);
$totalRevenue   = dbRow("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'")['total'] ?? 0;
$totalGuests    = dbRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'user'")['cnt'] ?? 0;
$totalStaff     = dbRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'staff'")['cnt'] ?? 0;
$totalBookings  = dbRow("SELECT COUNT(*) as cnt FROM bookings")['cnt'] ?? 0;

// Staff Audit Logs (Dedicated)
$staffAudits = dbRows("
    SELECT a.*, u.email, u.first_name, u.last_name, u.role 
    FROM audit_logs a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.role = 'staff'
    ORDER BY a.id DESC LIMIT 8
");

// User / Guest Audit Logs (Dedicated)
$userAudits = dbRows("
    SELECT a.*, u.email, u.first_name, u.last_name, u.role 
    FROM audit_logs a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.role = 'user'
    ORDER BY a.id DESC LIMIT 8
");

// All Recent Audit Logs
$recentAudits = dbRows("
    SELECT a.*, u.email, u.first_name, u.last_name, u.role 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC LIMIT 8
");

// Recent Flagged AI interactions (Security threats)
$securityAlerts = dbRows("SELECT * FROM ai_logs WHERE flagged = 1 ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(false); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
  <!-- Admin Sidebar -->
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <!-- Admin Main Panel -->
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button type="button" class="sidebar-collapse-btn" data-sidebar-collapse title="Toggle Compact Sidebar" aria-label="Toggle Compact Sidebar" aria-expanded="true"><i class="fa-solid fa-bars-staggered"></i></button>
        <div class="topbar-title">Control Hub &amp; Executive Management</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-gold"><i class="fa-solid fa-crown"></i> Full Administrator Access</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Executive Dashboard</h1>
          <div class="page-header-sub">Complete overview of hotel status, revenue, and staff activity.</div>
        </div>
        <div style="display:flex;gap:10px;">
          <a href="rooms.php" class="btn btn-gold btn-sm"><i class="fa-solid fa-plus"></i> Add Room</a>
          <a href="staff.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-user-plus"></i> Add Staff</a>
        </div>
      </div>

      <!-- Main Financial & Operational Cards -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fa-solid fa-wallet"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= formatCurrency($totalRevenue) ?></div>
            <div class="stat-label">Total Verified Revenue</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $totalBookings ?></div>
            <div class="stat-label">Total Recorded Bookings</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $totalGuests ?></div>
            <div class="stat-label">Registered Users</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $flaggedAiLogs ?></div>
            <div class="stat-label">AI Security Warnings</div>
          </div>
        </div>
      </div>

      <!-- Marvi Executive AI Business Intelligence & Revenue Digest -->
      <div class="glass-card" style="padding:22px;margin-bottom:28px;border:1px solid rgba(201,168,76,0.3);background:linear-gradient(135deg, rgba(16,26,46,0.95), rgba(26,38,68,0.9));">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:12px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));display:flex;align-items:center;justify-content:center;color:var(--navy);font-size:1.1rem;font-weight:700;">
              <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
              <div style="font-weight:700;color:#fff;font-size:1.05rem;">Marvi Executive AI — Business Intelligence &amp; Revenue Digest</div>
              <div style="font-size:0.75rem;color:var(--gold);">Strategic Financial Analysis &amp; Occupancy Optimization</div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="btnRefreshAdminAi" onclick="loadAdminAiInsights(true)" style="font-size:0.78rem;">
            <i class="fa-solid fa-arrows-rotate"></i> Refresh AI Analysis
          </button>
        </div>

        <div id="adminAiLoading" style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.88rem;">
          <i class="fa-solid fa-circle-notch fa-spin" style="color:var(--gold);margin-right:8px;"></i> Nagco-compute at nag-aanalisa ng hotel business metrics ang AI engine...
        </div>

        <div id="adminAiContent" style="display:none;font-size:0.88rem;line-height:1.7;color:#e2e8f0;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:16px;">
        </div>
      </div>

      <!-- Security / Flagged Bad Intent Table -->
      <?php if (!empty($securityAlerts)): ?>
      <div class="card" style="margin-bottom:28px;border-color:rgba(233,69,96,0.3);">
        <div class="card-header" style="background:rgba(233,69,96,0.06);">
          <h2 class="card-title" style="color:var(--red);"><i class="fa-solid fa-shield-virus"></i> Logged Bad Intent &amp; Security Violations (AI Alert)</h2>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>User Query / Question</th>
                  <th>Reason / Flagged Tag</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($securityAlerts as $alert): ?>
                  <tr>
                    <td><?= formatDate($alert['created_at'], 'M d, Y h:i A') ?></td>
                    <td><code><?= h($alert['user_message']) ?></code></td>
                    <td><span class="badge badge-danger"><?= h($alert['flag_reason']) ?></span></td>
                    <td><span class="badge badge-warning">Automatically Warned</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Audit Logs Table (Separated by Staff and User) -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
          <div>
            <h2 class="card-title" style="margin:0;"><i class="fa-solid fa-clipboard-list" style="color:var(--gold);margin-right:8px;"></i> Latest System Audit Logs</h2>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:3px;">Separated audit records to isolate Frontdesk Staff operations and User activity.</div>
          </div>
          <div class="audit-tab-pills">
            <button type="button" class="tab-pill active" onclick="switchAuditTab('staff', this)">
              <i class="fa-solid fa-user-tie" style="color:#4E9AF1;"></i> Staff Only <span class="pill-count"><?= count($staffAudits) ?></span>
            </button>
            <button type="button" class="tab-pill" onclick="switchAuditTab('user', this)">
              <i class="fa-solid fa-user" style="color:#2ECC71;"></i> User Only <span class="pill-count"><?= count($userAudits) ?></span>
            </button>
            <button type="button" class="tab-pill" onclick="switchAuditTab('all', this)">
              <i class="fa-solid fa-list-check" style="color:var(--gold);"></i> All Logs <span class="pill-count"><?= count($recentAudits) ?></span>
            </button>
          </div>
        </div>

        <div class="card-body" style="padding:0;">
          <!-- Pane 1: Staff Only -->
          <div id="audit-tab-staff" class="audit-tab-pane">
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Staff Member</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>IP Address</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($staffAudits)): ?>
                    <tr>
                      <td colspan="5" class="text-center" style="padding:28px;color:var(--text-muted);">
                        <i class="fa-solid fa-user-tie" style="font-size:1.5rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                        No staff audit logs found.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($staffAudits as $log): ?>
                      <tr>
                        <td><?= formatDate($log['created_at'], 'M d, Y h:i A') ?></td>
                        <td>
                          <div style="display:flex;align-items:center;gap:10px;">
                            <span class="badge" style="background:rgba(78,154,241,0.18);color:#4E9AF1;border:1px solid rgba(78,154,241,0.3);padding:3px 8px;font-size:0.7rem;"><i class="fa-solid fa-user-tie"></i> Staff</span>
                            <div>
                              <div style="font-weight:700;color:#fff;font-size:0.88rem;"><?= h(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Staff Frontdesk') ?></div>
                              <div style="font-size:0.78rem;color:var(--text-muted);"><?= h($log['email']) ?></div>
                            </div>
                          </div>
                        </td>
                        <td><strong><?= h($log['action']) ?></strong></td>
                        <td><span class="badge badge-secondary"><?= h($log['module']) ?></span></td>
                        <td><code><?= h($log['ip_address']) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pane 2: User Only -->
          <div id="audit-tab-user" class="audit-tab-pane" style="display:none;">
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>User Account</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>IP Address</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($userAudits)): ?>
                    <tr>
                      <td colspan="5" class="text-center" style="padding:28px;color:var(--text-muted);">
                        <i class="fa-solid fa-users" style="font-size:1.5rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                        No user audit logs found.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($userAudits as $log): ?>
                      <tr>
                        <td><?= formatDate($log['created_at'], 'M d, Y h:i A') ?></td>
                        <td>
                          <div style="display:flex;align-items:center;gap:10px;">
                            <span class="badge" style="background:rgba(46,204,113,0.18);color:#2ECC71;border:1px solid rgba(46,204,113,0.3);padding:3px 8px;font-size:0.7rem;"><i class="fa-solid fa-user"></i> User</span>
                            <div>
                              <div style="font-weight:700;color:#fff;font-size:0.88rem;"><?= h(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'User Account') ?></div>
                              <div style="font-size:0.78rem;color:var(--text-muted);"><?= h($log['email']) ?></div>
                            </div>
                          </div>
                        </td>
                        <td><strong><?= h($log['action']) ?></strong></td>
                        <td><span class="badge badge-secondary"><?= h($log['module']) ?></span></td>
                        <td><code><?= h($log['ip_address']) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pane 3: All Logs -->
          <div id="audit-tab-all" class="audit-tab-pane" style="display:none;">
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Account</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>IP Address</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentAudits)): ?>
                    <tr>
                      <td colspan="6" class="text-center" style="padding:28px;color:var(--text-muted);">No audit logs found.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recentAudits as $log): ?>
                      <?php 
                        $role = $log['role'] ?? null;
                        if (!$role) {
                            if (stripos($log['module'], 'staff') !== false) $role = 'staff';
                            elseif (stripos($log['module'], 'admin') !== false) $role = 'admin';
                            else $role = 'user';
                        }
                      ?>
                      <tr>
                        <td><?= formatDate($log['created_at'], 'M d, Y h:i A') ?></td>
                        <td><?= h($log['email'] ?: 'User / System') ?></td>
                        <td>
                          <?php if ($role === 'staff'): ?>
                            <span class="badge" style="background:rgba(78,154,241,0.18);color:#4E9AF1;border:1px solid rgba(78,154,241,0.3);padding:2px 7px;font-size:0.7rem;"><i class="fa-solid fa-user-tie"></i> Staff</span>
                          <?php elseif ($role === 'admin'): ?>
                            <span class="badge" style="background:rgba(201,168,76,0.18);color:var(--gold);border:1px solid rgba(201,168,76,0.3);padding:2px 7px;font-size:0.7rem;"><i class="fa-solid fa-crown"></i> Admin</span>
                          <?php else: ?>
                            <span class="badge" style="background:rgba(46,204,113,0.18);color:#2ECC71;border:1px solid rgba(46,204,113,0.3);padding:2px 7px;font-size:0.7rem;"><i class="fa-solid fa-user"></i> User</span>
                          <?php endif; ?>
                        </td>
                        <td><strong><?= h($log['action']) ?></strong></td>
                        <td><span class="badge badge-secondary"><?= h($log['module']) ?></span></td>
                        <td><code><?= h($log['ip_address']) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div style="padding:14px 24px;background:rgba(0,0,0,0.18);border-top:1px solid var(--border-subtle);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
          <span style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-circle-info" style="color:var(--gold);margin-right:6px;"></i> Showing up to 8 recent entries per role view.</span>
          <a href="audit-logs.php" class="btn btn-outline btn-sm" style="font-size:0.78rem;padding:5px 12px;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full Audit Archive &rarr;</a>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
function loadAdminAiInsights(forceRefresh = false) {
  const loading = document.getElementById('adminAiLoading');
  const content = document.getElementById('adminAiContent');
  const btn = document.getElementById('btnRefreshAdminAi');

  loading.style.display = 'block';
  content.style.display = 'none';
  if (btn) btn.disabled = true;

  fetch('../ai/admin-insights.php' + (forceRefresh ? '?refresh=1' : ''), { credentials: 'same-origin' })
    .then(res => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    })
    .then(data => {
      loading.style.display = 'none';
      if (data.success && data.report) {
        content.innerHTML = data.report;
        content.style.display = 'block';
      } else {
        content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Hindi ma-load ang business insights sa ngayon.</span>';
        content.style.display = 'block';
      }
    })
    .catch(() => {
      loading.style.display = 'none';
      content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> May error sa pag-konekta sa AI server.</span>';
      content.style.display = 'block';
    })
    .finally(() => {
      if (btn) btn.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  loadAdminAiInsights();
});
</script>
<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
