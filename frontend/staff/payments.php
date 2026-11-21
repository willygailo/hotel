<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

$staffId = currentUserId();
$staff = dbRow("SELECT * FROM users WHERE id = ?", [$staffId]);

$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'pending', 'verified', 'rejected'], true)) {
    $statusFilter = 'all';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$buildQs = function () use ($statusFilter, $page): string {
    $p = [];
    if ($statusFilter !== 'all') $p['status'] = $statusFilter;
    if ($page > 1) $p['page'] = $page;
    return !empty($p) ? ('?' . http_build_query($p)) : '';
};

// Action: Verify / Reject payment receipt (Delegated to Frontdesk Staff when Admin is away)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payment'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $payId = (int)($_POST['payment_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'verify' or 'reject'
        $res = processPaymentDecision($payId, $act, $staffId, 'Staff');
        setFlash($res['flash_type'], $res['message']);
    }
    header("Location: payments.php" . $buildQs());
    exit;
}

// Single-pass status counts for filter tabs & sidebar sync
$payCounts = dbRow("
    SELECT
        COUNT(*) AS all_cnt,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified_cnt,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_cnt
    FROM payments
") ?: ['all_cnt' => 0, 'pending_cnt' => 0, 'verified_cnt' => 0, 'rejected_cnt' => 0];

$pendingPayments = (int)($payCounts['pending_cnt'] ?? 0);
$GLOBALS['pendingPayments'] = $pendingPayments;

$whereSql = '';
$queryParams = [];
if ($statusFilter !== 'all') {
    $whereSql = 'WHERE p.status = ?';
    $queryParams[] = $statusFilter;
}

$totalFiltered = match ($statusFilter) {
    'pending'  => (int)($payCounts['pending_cnt'] ?? 0),
    'verified' => (int)($payCounts['verified_cnt'] ?? 0),
    'rejected' => (int)($payCounts['rejected_cnt'] ?? 0),
    default    => (int)($payCounts['all_cnt'] ?? 0),
};
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$payments = dbRows("
    SELECT p.*, b.booking_code,
           u.first_name, u.middle_name, u.last_name, u.suffix, u.email, u.phone,
           r.name as room_name, r.room_number,
           v.first_name as verifier_first_name, v.last_name as verifier_last_name, v.role as verifier_role
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON p.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN users v ON p.verified_by = v.id
    {$whereSql}
    ORDER BY (p.status = 'pending') DESC, p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $queryParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Verification — Frontdesk Staff</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/staff.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('staff'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="staff-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Frontdesk Payment Approvals</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-info"><i class="fa-solid fa-clock-rotate-left"></i> Staff Delegation</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <!-- Frontdesk Notice Banner -->
      <div style="background:rgba(78,154,241,0.08);border:1px solid rgba(78,154,241,0.25);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(78,154,241,0.2);display:flex;align-items:center;justify-content:center;color:#60a5fa;font-size:1.2rem;">
            <i class="fa-solid fa-shield-halved"></i>
          </div>
          <div>
            <div style="font-weight:700;color:#fff;font-size:0.95rem;">Frontdesk Payment Delegation Active</div>
            <div style="font-size:0.8rem;color:var(--text-muted);">You can verify online receipts (Maya / GCash) and over-the-counter payments while the Super Admin is away.</div>
          </div>
        </div>
        <div>
          <span class="badge" style="background:#eab308;color:#000;font-size:0.8rem;padding:6px 12px;font-weight:700;">
            <i class="fa-solid fa-bell"></i> <?= $pendingPayments ?> Pending Verification
          </span>
        </div>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Payment Verification Queue</h1>
          <div class="page-header-sub">Review receipts submitted by arriving customers and verify immediately.</div>
        </div>
      </div>

      <div class="staff-toolbar">
        <div class="staff-filter-pills">
          <a href="payments.php?status=all" class="staff-filter-pill<?= $statusFilter === 'all' ? ' active' : '' ?>">
            All <span class="pill-count"><?= (int)$payCounts['all_cnt'] ?></span>
          </a>
          <a href="payments.php?status=pending" class="staff-filter-pill<?= $statusFilter === 'pending' ? ' active' : '' ?>">
            Pending <span class="pill-count"><?= (int)$payCounts['pending_cnt'] ?></span>
          </a>
          <a href="payments.php?status=verified" class="staff-filter-pill<?= $statusFilter === 'verified' ? ' active' : '' ?>">
            Verified <span class="pill-count"><?= (int)$payCounts['verified_cnt'] ?></span>
          </a>
          <a href="payments.php?status=rejected" class="staff-filter-pill<?= $statusFilter === 'rejected' ? ' active' : '' ?>">
            Rejected <span class="pill-count"><?= (int)$payCounts['rejected_cnt'] ?></span>
          </a>
        </div>

        <div style="min-width:260px;">
          <input type="text" id="paySearchInput" data-table-search data-table-target="#paymentsTable" class="form-control" placeholder="Search booking, customer, or ref #..." style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="paymentsTable">
              <thead>
                <tr>
                  <th class="col-nowrap">Booking ID</th>
                  <th>Customer Name &amp; Contact</th>
                  <th class="col-nowrap">Room</th>
                  <th class="col-nowrap">Method</th>
                  <th class="col-amount">Amount</th>
                  <th class="col-nowrap">Ref # / Proof</th>
                  <th class="col-nowrap">Date &amp; Time Paid</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Frontdesk Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr data-empty-row><td colspan="9" class="text-center" style="padding:30px;color:var(--text-muted);">No payment records found for this filter.</td></tr>
                <?php else: ?>
                  <?php foreach ($payments as $pay): ?>
                    <tr>
                      <td class="col-nowrap"><strong>#<?= h($pay['booking_code']) ?></strong></td>
                      <td>
                        <strong><?= h(formatFullName($pay)) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                          <span><i class="fa-solid fa-phone"></i> <?= h($pay['phone'] ?: 'No Phone') ?></span>
                          <?php if (!empty($pay['email'])): ?>
                            <span>&bull; <?= h($pay['email']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="col-nowrap">Room <?= h($pay['room_number']) ?><br><small style="color:var(--text-muted);"><?= h($pay['room_name']) ?></small></td>
                      <td class="col-nowrap"><span style="text-transform:uppercase;font-weight:600;color:#60a5fa;"><?= h($pay['method']) ?></span></td>
                      <td class="col-amount" style="color:var(--gold);font-weight:700;"><?= formatCurrency($pay['amount']) ?></td>
                      <td class="col-nowrap">
                        <div><code><?= h($pay['reference_number'] ?: 'No Ref') ?></code></div>
                        <?php if ($pay['proof_image']): ?>
                          <a href="<?= h(getUploadUrl($pay['proof_image'])) ?>" target="_blank" style="color:var(--accent);font-size:0.75rem;"><i class="fa-solid fa-image"></i> View Receipt</a>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">
                        <div style="font-weight:600;color:#fff;"><?= formatDate($pay['created_at'], 'M d, Y') ?></div>
                        <div style="font-size:0.75rem;color:var(--gold);margin-top:2px;">
                          <i class="fa-solid fa-clock"></i> <?= formatDate($pay['created_at'], 'h:i:s A') ?>
                        </div>
                        <?php if (!empty($pay['verified_at']) && $pay['status'] === 'verified'): ?>
                          <div style="font-size:0.75rem;color:#4ade80;margin-top:3px;">
                            <i class="fa-solid fa-circle-check"></i> Approved: <?= formatDate($pay['verified_at'], 'M d, Y h:i A') ?>
                            <?php if (!empty($pay['verifier_first_name'])): ?>
                              <div style="margin-top:3px;">
                                <?php if (($pay['verifier_role'] ?? '') === 'admin'): ?>
                                  <span class="badge" style="background:rgba(234,179,8,0.15);color:#fde047;border:1px solid rgba(234,179,8,0.3);font-size:0.68rem;padding:2px 6px;">
                                    <i class="fa-solid fa-crown"></i> Super Admin: <?= h($pay['verifier_first_name'] . ' ' . ($pay['verifier_last_name'] ?? '')) ?>
                                  </span>
                                <?php else: ?>
                                  <span class="badge" style="background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3);font-size:0.68rem;padding:2px 6px;">
                                    <i class="fa-solid fa-user-tie"></i> Frontdesk Staff: <?= h($pay['verifier_first_name'] . ' ' . ($pay['verifier_last_name'] ?? '')) ?>
                                  </span>
                                <?php endif; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($pay['status']) ?></td>
                      <td class="col-nowrap">
                        <div style="display:inline-flex;gap:6px;align-items:center;">
                          <?php if ($pay['proof_image']): ?>
                            <button type="button" class="btn btn-ghost btn-sm" onclick='openStaffInspector(<?= json_encode([
                              'id' => $pay['id'],
                              'guest' => formatFullName($pay),
                              'booking_code' => $pay['booking_code'],
                              'room' => $pay['room_name'] . ' (Room #' . $pay['room_number'] . ')',
                              'method' => strtoupper($pay['method']),
                              'amount' => formatCurrency($pay['amount']),
                              'ref_num' => $pay['reference_number'] ?: 'No Reference Number',
                              'status' => $pay['status'],
                              'proof_url' => getUploadUrl($pay['proof_image']),
                              'proof_raw' => $pay['proof_image'],
                              'paid_at' => formatDate($pay['created_at'], 'F d, Y \a\t h:i:s A'),
                              'notes' => $pay['notes'] ?: 'No notes provided.',
                              'verified_by' => !empty($pay['verifier_first_name']) ? ((($pay['verifier_role'] ?? '') === 'admin' ? 'Super Admin: ' : 'Frontdesk Staff: ') . $pay['verifier_first_name'] . ' ' . ($pay['verifier_last_name'] ?? '')) : null,
                              'verified_at' => !empty($pay['verified_at']) ? formatDate($pay['verified_at'], 'M d, Y \a\t h:i A') : null
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Inspect Receipt" style="padding:4px 8px;font-size:0.75rem;">
                              <i class="fa-solid fa-magnifying-glass"></i> Inspect
                            </button>
                          <?php endif; ?>

                          <?php if ($pay['status'] === 'pending'): ?>
                            <form action="payments.php<?= h($buildQs()) ?>" method="POST" style="display:inline-flex;gap:4px;">
                              <?= csrfField() ?>
                              <input type="hidden" name="payment_id" value="<?= (int)$pay['id'] ?>">
                              <input type="hidden" name="action_payment" value="1">
                              <button type="submit" name="action_type" value="verify" class="btn btn-success btn-sm" title="Approve Payment" onclick="return confirm('Approve this payment for Booking #<?= h($pay['booking_code']) ?>?')"><i class="fa-solid fa-check"></i></button>
                              <button type="submit" name="action_type" value="reject" class="btn btn-danger btn-sm" title="Reject Payment" onclick="return confirm('Decline this payment receipt?')"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                          <?php else: ?>
                            <span style="font-size:0.75rem;color:var(--text-muted);margin-left:4px;">Processed</span>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-no-match-row style="display:none;"><td colspan="9" class="text-center" style="padding:28px;color:var(--text-muted);">No payments match your search term.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="staff-pagination">
              <span>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalFiltered ?> record(s))</span>
              <div class="staff-pagination-links">
                <?php if ($page > 1): ?>
                  <a href="payments.php?<?= http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'page' => $page - 1])) ?>" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="payments.php?<?= http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'page' => $page + 1])) ?>" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Receipt Lightbox Modal for Staff -->
<div class="inspector-modal staff-modal-overlay" id="staffInspectorModal">
  <div class="inspector-box" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:12px;">
      <h3 style="font-family:var(--font-serif);font-size:1.3rem;color:#fff;margin:0;">
        <i class="fa-solid fa-receipt" style="color:var(--gold);margin-right:8px;"></i> Frontdesk Receipt Verification
      </h3>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeStaffInspector()" style="font-size:1.2rem;padding:4px 8px;color:#94a3b8;">&times;</button>
    </div>

    <div class="staff-modal-grid-2">
      <div style="background:rgba(0,0,0,0.4);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;">
        <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;font-weight:700;">Uploaded Receipt</div>
        <img id="staffReceiptImg" src="#" alt="Proof Receipt" style="max-width:100%;max-height:360px;border-radius:8px;display:block;margin:0 auto;box-shadow:0 4px 15px rgba(0,0,0,0.5);object-fit:contain;">
        <div id="staffReceiptFallback" style="display:none;padding:28px 16px;color:var(--text-muted);font-size:0.85rem;">
          <i class="fa-solid fa-file-pdf" style="font-size:2.4rem;color:var(--gold);margin-bottom:8px;display:block;"></i>
          <span>Inline preview unavailable for this file. Open full file in a new tab below.</span>
        </div>
        <div style="margin-top:10px;">
          <a href="#" id="staffReceiptLink" target="_blank" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full File
          </a>
        </div>
      </div>

      <div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px;font-size:0.85rem;">
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Customer:</span>
            <strong style="color:#fff;" id="staffGuestName">-</strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Booking:</span>
            <span style="color:#60a5fa;font-weight:600;" id="staffBookingCode">-</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Room:</span>
            <span style="color:#fff;" id="staffRoomName">-</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Method:</span>
            <span style="color:#00e074;font-weight:700;" id="staffMethod">-</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Amount Due:</span>
            <strong style="color:var(--gold);font-size:1.1rem;" id="staffAmount">-</strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span style="color:var(--text-muted);">Timestamp Paid:</span>
            <span style="color:#fde047;font-weight:600;" id="staffPaidAt">-</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;">
            <span style="color:var(--text-muted);">Reference #:</span>
            <code style="color:#facc15;font-weight:700;" id="staffRefNum">-</code>
          </div>
        </div>

        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:16px;font-size:0.8rem;color:#cbd5e1;">
          <div style="color:var(--text-muted);font-size:0.75rem;margin-bottom:4px;">Customer Notes:</div>
          <div id="staffNotes">-</div>
        </div>

        <div id="staffActionArea">
          <form id="staffActionForm" action="payments.php<?= h($buildQs()) ?>" method="POST" style="display:flex;gap:10px;">
            <?= csrfField() ?>
            <input type="hidden" name="payment_id" id="staffPaymentId" value="">
            <input type="hidden" name="action_payment" value="1">
            <button type="submit" name="action_type" value="verify" class="btn btn-success" style="flex:1;" onclick="return confirm('Confirm and approve this receipt?')">
              <i class="fa-solid fa-check"></i> Approve Payment
            </button>
            <button type="submit" name="action_type" value="reject" class="btn btn-danger" style="flex:1;" onclick="return confirm('Decline this receipt?')">
              <i class="fa-solid fa-xmark"></i> Reject Payment
            </button>
          </form>
          <div id="staffStatusNotice" style="display:none;text-align:center;color:#4ade80;font-size:0.85rem;padding:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);border-radius:8px;font-weight:600;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
<script>
function openStaffInspector(data) {
  document.getElementById('staffPaymentId').value = data.id;
  document.getElementById('staffGuestName').textContent = data.guest;
  document.getElementById('staffBookingCode').textContent = '#' + data.booking_code;
  document.getElementById('staffRoomName').textContent = data.room;
  document.getElementById('staffMethod').textContent = data.method;
  document.getElementById('staffAmount').textContent = data.amount;
  document.getElementById('staffPaidAt').textContent = data.paid_at;
  document.getElementById('staffRefNum').textContent = data.ref_num;
  document.getElementById('staffNotes').textContent = data.notes || 'No notes provided.';

  const imgEl = document.getElementById('staffReceiptImg');
  const fallbackEl = document.getElementById('staffReceiptFallback');
  const linkEl = document.getElementById('staffReceiptLink');
  const rawName = (data.proof_raw || data.proof_url || '').toLowerCase();
  const isPdf = rawName.endsWith('.pdf');

  if (data.proof_url && !isPdf) {
    if (fallbackEl) fallbackEl.style.display = 'none';
    imgEl.onerror = function () {
      imgEl.style.display = 'none';
      if (fallbackEl) fallbackEl.style.display = 'block';
    };
    imgEl.src = data.proof_url;
    imgEl.style.display = 'block';
    linkEl.href = data.proof_url;
    linkEl.style.display = 'inline-flex';
  } else if (data.proof_url) {
    imgEl.style.display = 'none';
    if (fallbackEl) fallbackEl.style.display = 'block';
    linkEl.href = data.proof_url;
    linkEl.style.display = 'inline-flex';
  } else {
    imgEl.style.display = 'none';
    if (fallbackEl) fallbackEl.style.display = 'none';
    linkEl.href = '#';
    linkEl.style.display = 'none';
  }

  const formEl = document.getElementById('staffActionForm');
  const noticeEl = document.getElementById('staffStatusNotice');
  if (data.status !== 'pending') {
    let verifierNotice = '';
    if (data.verified_by) {
      verifierNotice = '<div style="margin-top:6px;font-size:0.8rem;color:#fde047;"><i class="fa-solid fa-user-check"></i> ' + escapeHtml(data.verified_by) + (data.verified_at ? ' (' + escapeHtml(data.verified_at) + ')' : '') + '</div>';
    }
    noticeEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> This payment is already ' + escapeHtml(String(data.status).toUpperCase()) + '.' + verifierNotice;
    formEl.style.display = 'none';
    noticeEl.style.display = 'block';
  } else {
    formEl.style.display = 'flex';
    noticeEl.style.display = 'none';
    noticeEl.innerHTML = '';
  }

  document.getElementById('staffInspectorModal').style.display = 'flex';
}

function closeStaffInspector() {
  document.getElementById('staffInspectorModal').style.display = 'none';
}
</script>
</body>
</html>
