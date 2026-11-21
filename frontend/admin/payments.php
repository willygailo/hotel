<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

$adminId = currentUserId();

// Handle verify / reject payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payment'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $payId = (int)($_POST['payment_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'verify' or 'reject'
        $res = processPaymentDecision($payId, $act, $adminId, 'Admin');
        setFlash($res['flash_type'], $res['message']);
    }
    header("Location: payments.php");
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$whereSql = '';
$queryParams = [];
if (in_array($statusFilter, ['pending', 'verified', 'rejected', 'refunded'], true)) {
    $whereSql = 'WHERE p.status = ?';
    $queryParams[] = $statusFilter;
}

$payments = dbRows("
    SELECT p.*, b.booking_code, 
           u.first_name, u.middle_name, u.last_name, u.suffix, u.email, u.phone, u.address, u.location, u.avatar, u.id_type, u.id_number, u.id_document, u.profile_verified, 
           r.name as room_name,
           v.first_name as verifier_first_name, v.last_name as verifier_last_name, v.role as verifier_role
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    JOIN users u ON p.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN users v ON p.verified_by = v.id 
    {$whereSql}
    ORDER BY p.id DESC
", $queryParams);

// CSV Export with formula-injection neutralization
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Payment ID', 'Booking Code', 'Guest Name', 'Email', 'Room', 'Method', 'Amount (PHP)', 'Reference #', 'Status', 'Created At', 'Verified At']);
    $csvSafe = function (?string $v): string {
        $s = (string)$v;
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    };
    foreach ($payments as $row) {
        fputcsv($out, [
            $row['id'],
            $csvSafe($row['booking_code']),
            $csvSafe(formatFullName($row)),
            $csvSafe($row['email']),
            $csvSafe($row['room_name']),
            $csvSafe(strtoupper($row['method'])),
            number_format((float)$row['amount'], 2, '.', ''),
            $csvSafe($row['reference_number']),
            $csvSafe($row['status']),
            $row['created_at'],
            $row['verified_at'] ?? ''
        ]);
    }
    fclose($out);
    auditLog('Exported Payments CSV', 'Admin Module', "Exported " . count($payments) . " payment records (filter: {$statusFilter})");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Verification — Admin Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Payment Verification &amp; Audit</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
        <div>
          <h1 class="page-header-title">Payment Management and Verification</h1>
          <div class="page-header-sub">Review uploaded GCash, Maya, and bank transfer proofs before confirming.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <a href="payments.php?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-gold' : 'btn-ghost' ?>">All</a>
          <a href="payments.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-gold' : 'btn-ghost' ?>">Pending</a>
          <a href="payments.php?status=verified" class="btn btn-sm <?= $statusFilter === 'verified' ? 'btn-gold' : 'btn-ghost' ?>">Verified</a>
          <a href="payments.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-gold' : 'btn-ghost' ?>">Rejected</a>
          <a href="payments.php?status=<?= urlencode($statusFilter) ?>&export=csv" class="btn btn-ghost btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User Account</th>
                  <th>Booking</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Ref # / Proof</th>
                  <th>Date &amp; Time Paid</th>
                  <th>Status</th>
                  <th>Admin Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr><td colspan="9" class="text-center" style="padding:30px;color:var(--text-muted);">No payment records found.</td></tr>
                <?php else: ?>
                  <?php foreach ($payments as $pay): ?>
                    <tr>
                      <td class="col-nowrap">#<?= $pay['id'] ?></td>
                      <td>
                        <div class="user-account-inline">
                          <img src="<?= h(getUserAvatarUrl($pay['avatar'] ?? null)) ?>" class="user-account-avatar" alt="" onerror="this.src='../frontend/assets/images/avatars/default-avatar.png'; this.onerror=null;">
                          <div class="user-account-details">
                            <div class="user-account-top">
                              <strong style="color:#fff;"><?= h(formatFullName($pay)) ?></strong>
                              <?php if (!empty($pay['profile_verified'])): ?>
                                <span class="badge badge-success" style="font-size:0.62rem;padding:2px 5px;"><i class="fa-solid fa-circle-check"></i> Verified</span>
                              <?php else: ?>
                                <span class="badge badge-warning" style="font-size:0.62rem;padding:2px 5px;"><i class="fa-solid fa-clock"></i> Unverified</span>
                              <?php endif; ?>
                            </div>
                            <div class="user-account-sub">
                              <span><i class="fa-solid fa-envelope" style="font-size:0.7rem;"></i> <?= h($pay['email']) ?></span>
                              <?php if (!empty($pay['phone'])): ?>
                                <span>&bull; <?= h($pay['phone']) ?></span>
                              <?php endif; ?>
                            </div>
                            <?php if (!empty($pay['id_document'])): ?>
                              <div style="margin-top:2px;">
                                <a href="<?= h(getUploadUrl($pay['id_document'])) ?>" target="_blank" style="color:#4ade80;font-size:0.72rem;display:inline-flex;align-items:center;gap:4px;">
                                  <i class="fa-solid fa-id-card"></i> Valid ID (<?= h($pay['id_type'] ?: 'ID') ?>)
                                </a>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="col-nowrap"><code>#<?= h($pay['booking_code']) ?></code></td>
                      <td class="col-nowrap"><span class="badge" style="background:rgba(255,255,255,0.08);color:#fff;text-transform:uppercase;font-size:0.72rem;padding:3px 8px;"><?= h($pay['method']) ?></span></td>
                      <td class="col-amount"><?= formatCurrency($pay['amount']) ?></td>
                      <td class="col-nowrap">
                        <div><code><?= h($pay['reference_number'] ?: 'No Ref #') ?></code></div>
                        <?php if ($pay['proof_image']): ?>
                          <div style="margin-top:3px;">
                            <a href="<?= getUploadUrl($pay['proof_image']) ?>" target="_blank" style="color:var(--accent);font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                              <i class="fa-solid fa-image"></i> View Receipt
                            </a>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">
                        <div style="font-weight:600;color:#fff;"><?= formatDate($pay['created_at'], 'M d, Y') ?></div>
                        <div style="font-size:0.74rem;color:var(--gold);margin-top:2px;">
                          <i class="fa-solid fa-clock" style="margin-right:3px;"></i><?= formatDate($pay['created_at'], 'h:i A') ?>
                        </div>
                        <?php if (!empty($pay['verified_at']) && $pay['status'] === 'verified'): ?>
                          <div style="font-size:0.7rem;color:#4ade80;margin-top:2px;">
                            <i class="fa-solid fa-circle-check"></i> Verified: <?= formatDate($pay['verified_at'], 'M d, Y') ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($pay['status']) ?></td>
                      <td class="col-nowrap">
                        <div style="display:inline-flex;gap:6px;align-items:center;">
                          <?php if ($pay['proof_image']): ?>
                            <button type="button" class="btn btn-ghost btn-sm" onclick='openReceiptInspector(<?= json_encode([
                              'id' => $pay['id'],
                              'guest' => $pay['first_name'] . ' ' . $pay['last_name'],
                              'email' => $pay['email'],
                              'booking_code' => $pay['booking_code'],
                              'room' => $pay['room_name'],
                              'method' => strtoupper($pay['method']),
                              'amount' => formatCurrency($pay['amount']),
                              'ref_num' => $pay['reference_number'] ?: 'No Reference Number',
                              'status' => $pay['status'],
                              'proof_url' => getUploadUrl($pay['proof_image']),
                              'notes' => $pay['notes'] ?: 'No additional notes provided.',
                              'paid_at' => formatDate($pay['created_at'], 'F d, Y \a\t h:i:s A'),
                              'verified_by' => !empty($pay['verifier_first_name']) ? ((($pay['verifier_role'] ?? '') === 'admin' ? 'Super Admin: ' : 'Frontdesk Staff: ') . $pay['verifier_first_name'] . ' ' . ($pay['verifier_last_name'] ?? '')) : null,
                              'verified_at' => !empty($pay['verified_at']) ? formatDate($pay['verified_at'], 'M d, Y \a\t h:i A') : null
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Inspect & Verify with AI Assist" style="padding:4px 8px;font-size:0.75rem;">
                              <i class="fa-solid fa-magnifying-glass-chart"></i> Inspect
                            </button>
                          <?php endif; ?>

                          <?php if ($pay['status'] === 'pending'): ?>
                            <form action="" method="POST" style="display:inline-flex;gap:4px;">
                              <?= csrfField() ?>
                              <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                              <input type="hidden" name="action_payment" value="1">
                              <button type="submit" name="action_type" value="verify" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i></button>
                              <button type="submit" name="action_type" value="reject" class="btn btn-danger btn-sm"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                          <?php else: ?>
                            <span style="font-size:0.75rem;color:var(--text-muted);margin-left:4px;">Done</span>
                          <?php endif; ?>
                        </div>
                      </td>
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

<!-- Receipt Quick Inspector Modal -->
<div id="receiptInspectorModal" onclick="closeReceiptInspector()" style="display:none;position:fixed;inset:0;background:rgba(5,10,24,0.85);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div class="glass-card" style="max-width:850px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;background:var(--navy);border:1px solid rgba(201,168,76,0.3);border-radius:16px;box-shadow:0 25px 60px rgba(0,0,0,0.7);" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="width:36px;height:36px;border-radius:10px;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.1rem;">
          <i class="fa-solid fa-receipt"></i>
        </span>
        <div>
          <h3 style="color:#fff;font-size:1.2rem;margin:0;">Payment Verification &amp; Receipt Inspector</h3>
          <div style="font-size:0.78rem;color:var(--text-muted);">Side-by-side transaction audit for Admin</div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeReceiptInspector()" style="font-size:1.2rem;padding:4px 8px;color:#94a3b8;">&times;</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;" id="inspectorGrid">
      <!-- Screenshot Preview Column -->
      <div style="background:rgba(0,0,0,0.4);border:1px solid var(--border);border-radius:12px;padding:12px;text-align:center;">
        <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;font-weight:700;">Uploaded Proof Receipt</div>
        <img id="inspectorReceiptImg" src="#" alt="Proof Receipt" style="max-width:100%;max-height:380px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5);display:block;margin:0 auto;">
        <div style="margin-top:10px;">
          <a href="#" id="inspectorReceiptLink" target="_blank" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open High-Res Full Image
          </a>
        </div>
      </div>

      <!-- Transaction Comparison Column -->
      <div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px;">
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:12px;">Transaction Details</div>
          
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Customer Name:</span>
            <strong style="color:#fff;" id="inspectorGuestName">-</strong>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Booking Code:</span>
            <span style="color:#60a5fa;font-weight:600;" id="inspectorBookingCode">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Room Assigned:</span>
            <span style="color:#fff;" id="inspectorRoomName">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Channel:</span>
            <span style="color:#00e074;font-weight:700;" id="inspectorMethod">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Amount Due:</span>
            <strong style="color:var(--gold);font-size:1.1rem;" id="inspectorAmount">-</strong>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Timestamp Paid:</span>
            <span style="color:#fde047;font-weight:600;" id="inspectorPaidAt">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:0.85rem;">
            <span style="color:var(--text-muted);">Reference Number:</span>
            <code style="color:#facc15;font-weight:700;" id="inspectorRefNum">-</code>
          </div>
        </div>

        <!-- Notes -->
        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:18px;font-size:0.8rem;color:#cbd5e1;">
          <div style="color:var(--text-muted);font-size:0.75rem;margin-bottom:4px;">Customer Notes:</div>
          <div id="inspectorNotes">-</div>
        </div>

        <!-- Actions -->
        <div id="inspectorActionArea">
          <form id="inspectorActionForm" action="" method="POST" style="display:flex;gap:10px;">
            <?= csrfField() ?>
            <input type="hidden" name="payment_id" id="inspectorPaymentId" value="">
            <input type="hidden" name="action_payment" value="1">
            <button type="submit" name="action_type" value="verify" class="btn btn-success" style="flex:1;">
              <i class="fa-solid fa-check"></i> Approve &amp; Confirm Booking
            </button>
            <button type="submit" name="action_type" value="reject" class="btn btn-danger" style="flex:1;">
              <i class="fa-solid fa-xmark"></i> Reject Receipt
            </button>
          </form>
          <div id="inspectorStatusNotice" style="display:none;text-align:center;color:#4ade80;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);border-radius:8px;padding:12px;font-size:0.85rem;font-weight:600;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/admin.js"></script>
<script>
function openReceiptInspector(data) {
  document.getElementById('inspectorPaymentId').value = data.id;
  document.getElementById('inspectorGuestName').textContent = data.guest + ' (' + data.email + ')';
  document.getElementById('inspectorBookingCode').textContent = '#' + data.booking_code;
  document.getElementById('inspectorRoomName').textContent = data.room;
  document.getElementById('inspectorMethod').textContent = data.method;
  document.getElementById('inspectorAmount').textContent = data.amount;
  document.getElementById('inspectorRefNum').textContent = data.ref_num;
  document.getElementById('inspectorPaidAt').textContent = data.paid_at || 'N/A';
  document.getElementById('inspectorNotes').textContent = data.notes;

  const imgEl = document.getElementById('inspectorReceiptImg');
  const linkEl = document.getElementById('inspectorReceiptLink');
  if (data.proof_url) {
    imgEl.src = data.proof_url;
    imgEl.style.display = 'block';
    linkEl.href = data.proof_url;
    linkEl.style.display = 'inline-flex';
  } else {
    imgEl.style.display = 'none';
    linkEl.href = '#';
    linkEl.style.display = 'none';
  }

  const formEl = document.getElementById('inspectorActionForm');
  const noticeEl = document.getElementById('inspectorStatusNotice');
  if (data.status !== 'pending') {
    let verifierNotice = '';
    if (data.verified_by) {
      verifierNotice = '<div style="margin-top:6px;font-size:0.8rem;color:#fde047;"><i class="fa-solid fa-user-check"></i> Processed by ' + escapeHtml(data.verified_by) + (data.verified_at ? ' (' + escapeHtml(data.verified_at) + ')' : '') + '</div>';
    }
    noticeEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Ang transaction na ito ay tapos na (' + escapeHtml(String(data.status).toUpperCase()) + ').' + verifierNotice;
    formEl.style.display = 'none';
    noticeEl.style.display = 'block';
  } else {
    formEl.style.display = 'flex';
    noticeEl.style.display = 'none';
    noticeEl.innerHTML = '';
  }

  const modal = document.getElementById('receiptInspectorModal');
  modal.style.display = 'flex';
}

function closeReceiptInspector() {
  document.getElementById('receiptInspectorModal').style.display = 'none';
}
</script>
</body>
</html>
