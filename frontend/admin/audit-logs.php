<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

$filter = trim($_GET['role'] ?? 'all');
$whereClause = "";

if ($filter === 'user_auth') {
    $whereClause = "WHERE (u.role = 'user' OR a.module IN ('User Auth', 'Auth', 'User Module') OR a.action LIKE '%User%' OR a.details LIKE '%user%')";
} elseif ($filter === 'staff_auth') {
    $whereClause = "WHERE (u.role = 'staff' OR a.module IN ('Staff Auth', 'Staff Module') OR a.action LIKE '%Staff%' OR a.details LIKE '%staff%')";
} elseif ($filter === 'admin_auth') {
    $whereClause = "WHERE (u.role = 'admin' OR a.module IN ('Admin Auth', 'Admin Module') OR a.action LIKE '%Admin%')";
} elseif ($filter === 'failed') {
    $whereClause = "WHERE (a.action LIKE '%Failed%' OR a.action LIKE '%Blocked%' OR a.action LIKE '%WARNING%' OR a.details LIKE '%failed%' OR a.details LIKE '%invalid_credentials%' OR a.details LIKE '%banned%')";
} elseif ($filter === 'staff') {
    $whereClause = "WHERE u.role = 'staff' OR a.module LIKE '%Staff%'";
} elseif ($filter === 'user') {
    $whereClause = "WHERE u.role = 'user' OR a.module LIKE '%User%'";
}

$logs = dbRows("
    SELECT a.*, u.email as user_email, u.first_name, u.last_name, u.role as user_role 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    {$whereClause} 
    ORDER BY a.id DESC 
    LIMIT 200
");

// Accurate Tab Counts
$cntUserAuth  = (int)(dbRow("SELECT COUNT(*) as c FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE (u.role = 'user' OR a.module IN ('User Auth', 'Auth', 'User Module') OR a.action LIKE '%User%' OR a.details LIKE '%user%')")['c'] ?? 0);
$cntStaffAuth = (int)(dbRow("SELECT COUNT(*) as c FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE (u.role = 'staff' OR a.module IN ('Staff Auth', 'Staff Module') OR a.action LIKE '%Staff%' OR a.details LIKE '%staff%')")['c'] ?? 0);
$cntAdminAuth = (int)(dbRow("SELECT COUNT(*) as c FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE (u.role = 'admin' OR a.module IN ('Admin Auth', 'Admin Module') OR a.action LIKE '%Admin%')")['c'] ?? 0);
$cntFailed    = (int)(dbRow("SELECT COUNT(*) as c FROM audit_logs WHERE (action LIKE '%Failed%' OR action LIKE '%Blocked%' OR action LIKE '%WARNING%' OR details LIKE '%failed%' OR details LIKE '%invalid_credentials%' OR details LIKE '%banned%')")['c'] ?? 0);
$cntAll       = (int)(dbRow("SELECT COUNT(*) as c FROM audit_logs")['c'] ?? 0);

/**
 * Helper to parse raw JSON logs into clean human-readable details and email
 */
function parseAuditDetails(array $log): array {
    $details = $log['details'] ?? '';
    $action  = $log['action'] ?? '';
    $email   = $log['user_email'] ?? '';
    $name    = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
    $badgeType = 'info';

    // If details is JSON
    if (!empty($details) && ($details[0] === '{' || $details[0] === '[')) {
        $json = json_decode($details, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $msg = $json['message'] ?? '';
            $ctx = $json['context'] ?? [];
            if (empty($email) && !empty($ctx['email'])) {
                $email = $ctx['email'];
            }
            if (!empty($ctx['reason'])) {
                if ($ctx['reason'] === 'invalid_credentials') {
                    $details = 'Failed login: Incorrect password or email provided.';
                    $badgeType = 'danger';
                    if ($action === 'WARNING') $action = 'Login Failed';
                } elseif ($ctx['reason'] === 'banned') {
                    $details = 'Login rejected: Account is currently suspended/banned.';
                    $badgeType = 'danger';
                    if ($action === 'WARNING') $action = 'Account Suspended';
                } elseif ($ctx['reason'] === 'unverified') {
                    $details = 'Login rejected: Email address not yet verified.';
                    $badgeType = 'warning';
                    if ($action === 'WARNING') $action = 'Unverified Email';
                } else {
                    $details = "Auth notice: {$ctx['reason']}";
                }
            } else {
                $details = $msg ?: $details;
            }
        }
    }

    if (stripos($action, 'failed') !== false || stripos($details, 'failed') !== false) {
        $badgeType = 'danger';
    } elseif (stripos($action, 'blocked') !== false || stripos($action, 'rate limit') !== false) {
        $badgeType = 'warning';
    } elseif (stripos($action, 'registered') !== false) {
        $badgeType = 'success';
    } elseif (stripos($action, 'logged in') !== false) {
        $badgeType = 'success';
    }

    // Role detection
    $role = $log['user_role'] ?? '';
    if (empty($role)) {
        if (stripos($log['module'], 'staff') !== false || stripos($action, 'staff') !== false) {
            $role = 'staff';
        } elseif (stripos($log['module'], 'admin') !== false || stripos($action, 'admin') !== false) {
            $role = 'admin';
        } else {
            $role = 'user';
        }
    }

    return [
        'name'      => $name ?: ($email ?: 'Guest User'),
        'email'     => $email,
        'role'      => $role,
        'action'    => $action,
        'details'   => $details,
        'badgeType' => $badgeType,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit &amp; Login Logs — Admin Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/admin.css">
  <style>
    .audit-tab-pills { display: flex; gap: 8px; flex-wrap: wrap; }
    .audit-tab-pill {
      display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
      border-radius: 10px; font-size: 0.86rem; font-weight: 500; text-decoration: none;
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
      color: var(--text-muted); transition: all 0.2s ease;
    }
    .audit-tab-pill:hover { background: rgba(201,168,76,0.1); color: var(--gold); border-color: rgba(201,168,76,0.3); }
    .audit-tab-pill.active { background: var(--gold); color: #0d1238; font-weight: 700; border-color: var(--gold); }
    .audit-pill-count {
      background: rgba(0,0,0,0.25); color: inherit; padding: 2px 8px; border-radius: 999px; font-size: 0.72rem;
    }
    .audit-tab-pill.active .audit-pill-count { background: #0d1238; color: #fff; }
  </style>
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">System Audit &amp; Activity Trail</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
        <div>
          <h1 class="page-header-title">Audit, Login &amp; Registration Logs</h1>
          <div class="page-header-sub">Dedicated activity feeds for Guest Users, Staff Frontdesk, and Security Audits.</div>
        </div>
      </div>

      <!-- Dedicated Separated Activity Tabs -->
      <div class="audit-tab-pills" style="margin-bottom:20px;">
        <a href="?role=user_auth" class="audit-tab-pill <?= $filter === 'user_auth' ? 'active' : '' ?>">
          <i class="fa-solid fa-users" style="color:<?= $filter === 'user_auth' ? 'inherit' : '#2ECC71' ?>;"></i> 
          User Logins &amp; Signups <span class="audit-pill-count"><?= $cntUserAuth ?></span>
        </a>
        <a href="?role=staff_auth" class="audit-tab-pill <?= $filter === 'staff_auth' ? 'active' : '' ?>">
          <i class="fa-solid fa-user-tie" style="color:<?= $filter === 'staff_auth' ? 'inherit' : '#4E9AF1' ?>;"></i> 
          Staff Desk Activity <span class="audit-pill-count"><?= $cntStaffAuth ?></span>
        </a>
        <a href="?role=admin_auth" class="audit-tab-pill <?= $filter === 'admin_auth' ? 'active' : '' ?>">
          <i class="fa-solid fa-crown" style="color:<?= $filter === 'admin_auth' ? 'inherit' : 'var(--gold)' ?>;"></i> 
          Admin Actions <span class="audit-pill-count"><?= $cntAdminAuth ?></span>
        </a>
        <a href="?role=failed" class="audit-tab-pill <?= $filter === 'failed' ? 'active' : '' ?>" style="<?= $cntFailed > 0 ? 'border-color:rgba(239,68,68,0.4);' : '' ?>">
          <i class="fa-solid fa-triangle-exclamation" style="color:<?= $filter === 'failed' ? 'inherit' : '#ef4444' ?>;"></i> 
          Failed Attempts <span class="audit-pill-count" style="<?= $cntFailed > 0 && $filter !== 'failed' ? 'background:#ef4444;color:#fff;' : '' ?>"><?= $cntFailed ?></span>
        </a>
        <a href="?role=all" class="audit-tab-pill <?= $filter === 'all' ? 'active' : '' ?>">
          <i class="fa-solid fa-list-check"></i> 
          All Logs <span class="audit-pill-count"><?= $cntAll ?></span>
        </a>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th class="col-nowrap" style="min-width:160px;">Timestamp</th>
                  <th class="col-nowrap" style="min-width:180px;">Account / Identity</th>
                  <th class="col-nowrap">Role</th>
                  <th class="col-nowrap">Action Event</th>
                  <th class="col-nowrap">Module</th>
                  <th style="min-width:240px;">Activity Details</th>
                  <th class="col-nowrap">IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                  <tr>
                    <td colspan="7" class="text-center" style="padding:36px;color:var(--text-muted);">
                      <i class="fa-solid fa-folder-open" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                      No activity logs recorded under this filter.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($logs as $l): ?>
                    <?php $p = parseAuditDetails($l); ?>
                    <tr>
                      <td class="col-nowrap" style="font-size:0.84rem;color:#cbd5e1;">
                        <i class="fa-regular fa-clock" style="color:var(--gold);margin-right:4px;"></i>
                        <?= formatDate($l['created_at'], 'M d, Y h:i:s A') ?>
                      </td>
                      <td class="col-nowrap">
                        <strong style="color:#fff;"><?= h($p['name']) ?></strong>
                        <?php if (!empty($p['email'])): ?>
                          <div style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;"><?= h($p['email']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">
                        <?php if ($p['role'] === 'staff'): ?>
                          <span class="badge" style="background:rgba(78,154,241,0.18);color:#4E9AF1;border:1px solid rgba(78,154,241,0.3);padding:3px 8px;font-size:0.72rem;"><i class="fa-solid fa-user-tie"></i> Staff</span>
                        <?php elseif ($p['role'] === 'admin'): ?>
                          <span class="badge" style="background:rgba(201,168,76,0.18);color:var(--gold);border:1px solid rgba(201,168,76,0.3);padding:3px 8px;font-size:0.72rem;"><i class="fa-solid fa-crown"></i> Admin</span>
                        <?php else: ?>
                          <span class="badge" style="background:rgba(46,204,113,0.18);color:#2ECC71;border:1px solid rgba(46,204,113,0.3);padding:3px 8px;font-size:0.72rem;"><i class="fa-solid fa-user"></i> Guest</span>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">
                        <?php if ($p['badgeType'] === 'danger'): ?>
                          <span class="badge" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);padding:3px 8px;font-size:0.75rem;">
                            <i class="fa-solid fa-circle-xmark"></i> <?= h($p['action']) ?>
                          </span>
                        <?php elseif ($p['badgeType'] === 'warning'): ?>
                          <span class="badge" style="background:rgba(234,179,8,0.15);color:#facc15;border:1px solid rgba(234,179,8,0.3);padding:3px 8px;font-size:0.75rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i> <?= h($p['action']) ?>
                          </span>
                        <?php elseif ($p['badgeType'] === 'success'): ?>
                          <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);padding:3px 8px;font-size:0.75rem;">
                            <i class="fa-solid fa-circle-check"></i> <?= h($p['action']) ?>
                          </span>
                        <?php else: ?>
                          <strong style="color:#fff;"><?= h($p['action']) ?></strong>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><span class="badge badge-secondary" style="font-size:0.72rem;"><?= h($l['module']) ?></span></td>
                      <td style="font-size:0.84rem;color:#cbd5e1;line-height:1.4;">
                        <?= h($p['details']) ?>
                      </td>
                      <td class="col-nowrap"><code style="font-size:0.75rem;background:rgba(0,0,0,0.3);padding:2px 6px;border-radius:4px;"><?= h($l['ip_address']) ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/admin.js"></script>
</body>
</html>
