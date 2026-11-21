<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

// Add new staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $fn    = trim($_POST['first_name'] ?? '');
        $ln    = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['password'] ?? '';

        $exists = dbRow("SELECT id FROM users WHERE email = ?", [$email]);
        $nameErr = validatePersonName($fn, $ln);
        if ($nameErr !== null) {
            setFlash('error', $nameErr);
        } elseif ($exists) {
            setFlash('error', 'This email address is already in use.');
        } elseif (strlen($pass) < 8) {
            setFlash('error', 'The password must be at least 8 characters.');
        } else {
            $hashed = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            dbInsert(
                "INSERT INTO users (first_name, last_name, email, phone, password, role, status, email_verified) 
                 VALUES (?, ?, ?, ?, ?, 'staff', 'active', 1)",
                [$fn, $ln, $email, $phone, $hashed]
            );
            auditLog('Created Staff Account', 'Admin Module', "Created staff account for $email");
            setFlash('success', "Staff account for <strong>" . h($fn . ' ' . $ln) . "</strong> (" . h($email) . ") created successfully! The employee can now log in at the Staff Portal (<a href='" . h(STAFF_SITE_URL . '/login.php') . "' target='_blank' style='color:#fff;text-decoration:underline;'>" . h(STAFF_SITE_URL . '/login.php') . "</a>) to access their dashboard.");
        }
    }
    header("Location: staff.php");
    exit;
}

// Handle master password reset for staff by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $sId = (int)($_POST['user_id'] ?? 0);
    $newPass = $_POST['new_password'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } elseif (strlen($newPass) < 8) {
        setFlash('error', 'New password must be at least 8 characters long.');
    } else {
        $stf = dbRow("SELECT * FROM users WHERE id = ? AND role = 'staff'", [$sId]);
        if ($stf) {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            dbExec("UPDATE users SET password = ? WHERE id = ?", [$hashed, $sId]);
            auditLog('Admin Reset Staff Password', 'Admin Module', "Admin reset password for Staff #$sId ({$stf['email']})");
            setFlash('success', "Password for Staff {$stf['first_name']} {$stf['last_name']} ({$stf['email']}) was successfully updated!");
        } else {
            setFlash('error', 'Staff account not found.');
        }
    }
    header("Location: staff.php");
    exit;
}

// Handle Edit Staff Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $sId   = (int)($_POST['staff_id'] ?? 0);

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $stf = dbRow("SELECT * FROM users WHERE id = ? AND role = 'staff'", [$sId]);
        if (!$stf) {
            setFlash('error', 'Staff account not found.');
        } else {
            $fn     = trim($_POST['first_name'] ?? '');
            $ln     = trim($_POST['last_name'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $phone  = trim($_POST['phone'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

            $nameErr = validatePersonName($fn, $ln, $stf['middle_name'] ?? '', $stf['suffix'] ?? '', $sId);
            if ($nameErr !== null) {
                setFlash('error', $nameErr);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Please enter a valid email address.');
            } else {
                $emailExists = dbRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $sId]);
                if ($emailExists) {
                    setFlash('error', "The email '$email' is already in use by another account.");
                } else {
                    dbExec("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'staff'", [
                        $fn, $ln, $email, $phone, $status, $sId
                    ]);
                    auditLog('Admin Updated Staff Account', 'Admin Module', "Admin updated Staff #$sId ($email)");
                    setFlash('success', "Staff account for <strong>" . h("$fn $ln") . "</strong> ($email) updated successfully.");
                }
            }
        }
    }
    header("Location: staff.php");
    exit;
}

// Handle Delete Staff Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $sId   = (int)($_POST['staff_id'] ?? 0);

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $stf = dbRow("SELECT * FROM users WHERE id = ? AND role = 'staff'", [$sId]);
        if (!$stf) {
            setFlash('error', 'Staff account not found or already deleted.');
        } else {
            // Nullify audit references so foreign key won't conflict
            dbExec("UPDATE bookings SET accepted_by = NULL WHERE accepted_by = ?", [$sId]);
            dbExec("UPDATE payments SET verified_by = NULL WHERE verified_by = ?", [$sId]);

            dbExec("DELETE FROM users WHERE id = ? AND role = 'staff'", [$sId]);
            auditLog('Admin Deleted Staff Account', 'Admin Module', "Admin deleted Staff #$sId ({$stf['email']})");
            setFlash('success', "Staff account for <strong>" . h("{$stf['first_name']} {$stf['last_name']}") . "</strong> ({$stf['email']}) permanently deleted.");
        }
    }
    header("Location: staff.php");
    exit;
}

// Toggle status (CSRF-protected POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $sId = (int)($_POST['staff_id'] ?? 0);
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $stf = dbRow("SELECT * FROM users WHERE id = ? AND role = 'staff'", [$sId]);
        if ($stf) {
            $newStat = $stf['status'] === 'active' ? 'inactive' : 'active';
            dbExec("UPDATE users SET status = ? WHERE id = ?", [$newStat, $sId]);
            auditLog('Toggled Staff Status', 'Admin Module', "Staff #$sId status changed to $newStat");
            setFlash('info', "Staff status changed.");
        }
    }
    header("Location: staff.php");
    exit;
}

$staffMembers = dbRows("SELECT * FROM users WHERE role = 'staff' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Staff — Admin Portal</title>
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
        <div class="topbar-title">Staff Administration &amp; Roles</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Staff Management (Frontdesk)</h1>
          <div class="page-header-sub">Register and manage hotel personnel authorized to operate the Frontdesk and Check-in systems.</div>
        </div>
      </div>

      <!-- Staff Authority & Portal Guidance Banner -->
      <div style="background:rgba(78,154,241,0.08);border:1px solid rgba(78,154,241,0.25);border-radius:14px;padding:20px;margin-bottom:24px;display:flex;align-items:flex-start;gap:16px;">
        <div style="width:44px;height:44px;border-radius:10px;background:rgba(78,154,241,0.2);display:flex;align-items:center;justify-content:center;color:#60a5fa;font-size:1.3rem;flex-shrink:0;">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <div style="flex:1;">
          <h4 style="color:#fff;font-size:1rem;margin:0 0 6px;">Admin Authority: Staff Account Provisioning</h4>
          <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 12px;line-height:1.5;">
            Ang <strong>Admin lamang</strong> ang may kakayahang gumawa ng account para sa Staff. Kapag na-create na ang account, ibigay sa empleyado ang kanilang <strong>Email</strong> at <strong>Password</strong> upang mag-sign in sila sa <strong>Staff Frontdesk Portal</strong> at magka-access sa kanilang dashboard (check-in/out, room status, at customer assistance).
          </p>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:0.8rem;color:#94a3b8;">Staff Login Portal:</span>
            <code id="staffPortalUrlCode" style="background:rgba(0,0,0,0.4);padding:5px 12px;border-radius:6px;font-size:0.82rem;color:#60a5fa;"><?= h(STAFF_SITE_URL . '/login.php') ?></code>
            <button type="button" class="btn btn-ghost btn-sm" id="copyStaffLinkBtn" onclick="copyStaffPortalUrl()" style="padding:4px 10px;font-size:0.75rem;">
              <i class="fa-regular fa-copy"></i> Copy Link
            </button>
            <a href="<?= h(STAFF_SITE_URL . '/login.php') ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:0.75rem;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Staff Portal
            </a>
          </div>
        </div>
      </div>

      <!-- Add Staff Form -->
      <div class="glass-card" style="padding:24px;margin-bottom:28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin:0;">
            <i class="fa-solid fa-user-plus"></i> Create New Staff Account
          </h3>
          <span style="font-size:0.75rem;color:var(--text-muted);"><i class="fa-solid fa-lock"></i> Auto-verified &amp; active upon creation</span>
        </div>

        <form action="" method="POST" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;">
          <?= csrfField() ?>
          <input type="hidden" name="add_staff" value="1">
          <div>
            <label class="form-label">First Name <span class="required">*</span></label>
            <input type="text" name="first_name" id="newStaffFn" class="form-control" required placeholder="Maria" minlength="2" maxlength="50" pattern="^[a-zA-ZÀ-ÿ\s'\-\.]+$" title="Letters, spaces, hyphens and apostrophes only (2-50 chars)">
          </div>
          <div>
            <label class="form-label">Last Name <span class="required">*</span></label>
            <input type="text" name="last_name" id="newStaffLn" class="form-control" required placeholder="Santos" minlength="2" maxlength="50" pattern="^[a-zA-ZÀ-ÿ\s'\-\.]+$" title="Letters, spaces, hyphens and apostrophes only (2-50 chars)">
          </div>
          <div>
            <label class="form-label">Staff Email Address <span class="required">*</span></label>
            <input type="email" name="email" class="form-control" required placeholder="staff.maria@hotel.com">
          </div>
          <div>
            <label class="form-label">Contact Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="09123456789">
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
              <label class="form-label" style="margin:0;">Initial Password <span class="required">*</span></label>
              <button type="button" onclick="generateNewStaffPass()" style="background:none;border:none;color:var(--gold);font-size:0.72rem;cursor:pointer;padding:0;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Generate
              </button>
            </div>
            <div style="position:relative;">
              <input type="password" name="password" id="newStaffPassword" class="form-control" required placeholder="Min 8 characters" minlength="8" style="padding-right:38px;">
              <button type="button" onclick="togglePassVisibility('newStaffPassword', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>
          <button type="submit" class="btn btn-gold" style="height:44px;align-self:flex-end;">
            <i class="fa-solid fa-user-check"></i> Create Staff Account
          </button>
        </form>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th class="col-nowrap">ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th class="col-nowrap">Contact</th>
                  <th class="col-nowrap">Role</th>
                  <th class="col-nowrap">Last Login</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($staffMembers as $st): ?>
                  <tr>
                    <td class="col-nowrap">#<?= $st['id'] ?></td>
                    <td class="col-nowrap"><strong><?= h($st['first_name'] . ' ' . $st['last_name']) ?></strong></td>
                    <td><?= h($st['email']) ?></td>
                    <td class="col-nowrap"><?= h($st['phone'] ?: 'N/A') ?></td>
                    <td class="col-nowrap"><span class="badge badge-info">Frontdesk Staff</span></td>
                    <td class="col-nowrap"><?= $st['last_login'] ? formatDate($st['last_login'], 'M d, Y h:i A') : 'Never' ?></td>
                    <td class="col-nowrap"><?= statusBadge($st['status']) ?></td>
                    <td class="col-nowrap" style="white-space:nowrap;display:flex;gap:6px;align-items:center;">
                      <button type="button" class="btn btn-ghost btn-sm" onclick='openEditStaffModal(<?= json_encode([
                        "id" => $st["id"],
                        "first_name" => $st["first_name"],
                        "last_name" => $st["last_name"],
                        "email" => $st["email"],
                        "phone" => $st["phone"] ?? "",
                        "status" => $st["status"]
                      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Staff Details">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <button type="button" class="btn btn-gold btn-sm" onclick="openResetModal(<?= $st['id'] ?>, '<?= addslashes(h($st['first_name'] . ' ' . $st['last_name'])) ?>', '<?= addslashes(h($st['email'])) ?>')" title="Master Reset Password">
                        <i class="fa-solid fa-key"></i>
                      </button>
                      <form action="" method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
                        <button type="submit" class="btn <?= $st['status'] === 'active' ? 'btn-ghost' : 'btn-success' ?> btn-sm" title="<?= $st['status'] === 'active' ? 'Deactivate Staff' : 'Activate Staff' ?>">
                          <?= $st['status'] === 'active' ? '<i class="fa-solid fa-user-slash"></i>' : '<i class="fa-solid fa-user-check"></i>' ?>
                        </button>
                      </form>
                      <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete staff account for <?= addslashes(h($st['first_name'] . ' ' . $st['last_name'])) ?>? This cannot be undone!')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Staff Account">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Admin Edit Staff Modal -->
<div id="editStaffModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div class="glass-card" style="max-width:500px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.6);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid rgba(255,255,255,0.08);padding-bottom:12px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.3rem;margin:0;">
        <i class="fa-solid fa-user-pen"></i> Edit Staff Account
      </h3>
      <button type="button" onclick="closeEditStaffModal()" style="background:none;border:none;color:#aaa;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>

    <form action="" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_staff">
      <input type="hidden" name="staff_id" id="editStaffId">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">First Name <span class="required">*</span></label>
          <input type="text" name="first_name" id="editStaffFn" class="form-control" required>
        </div>
        <div>
          <label class="form-label">Last Name <span class="required">*</span></label>
          <input type="text" name="last_name" id="editStaffLn" class="form-control" required>
        </div>
      </div>

      <div style="margin-bottom:12px;">
        <label class="form-label">Email Address <span class="required">*</span></label>
        <input type="email" name="email" id="editStaffEmail" class="form-control" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
        <div>
          <label class="form-label">Contact Number</label>
          <input type="tel" name="phone" id="editStaffPhone" class="form-control">
        </div>
        <div>
          <label class="form-label">Account Status</label>
          <select name="status" id="editStaffStatus" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive / Suspended</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeEditStaffModal()">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save Staff Details</button>
      </div>
    </form>
  </div>
</div>

<!-- Master Reset Password Modal for Staff -->
<div id="resetPasswordModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div class="glass-card" style="max-width:440px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.25rem;margin:0;"><i class="fa-solid fa-key"></i> Master Staff Password Reset</h3>
      <button type="button" onclick="closeResetModal()" style="background:none;border:none;color:#aaa;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
      Directly set a new login password for Staff <strong id="modalUserName" style="color:#fff;"></strong> (<span id="modalUserEmail"></span>).
    </p>
    <form action="" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="modalUserId">

      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">New Password (Min. 8 chars) <span class="required">*</span></label>
        <div style="display:flex;gap:8px;">
          <input type="text" name="new_password" id="modalNewPassword" class="form-control" required minlength="8" placeholder="Enter new password">
          <button type="button" class="btn btn-ghost btn-sm" onclick="generateRandomPass()" style="white-space:nowrap;" title="Generate random secure password"><i class="fa-solid fa-dice"></i> Gen</button>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeResetModal()">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditStaffModal(data) {
  document.getElementById('editStaffId').value = data.id;
  document.getElementById('editStaffFn').value = data.first_name || '';
  document.getElementById('editStaffLn').value = data.last_name || '';
  document.getElementById('editStaffEmail').value = data.email || '';
  document.getElementById('editStaffPhone').value = data.phone || '';
  document.getElementById('editStaffStatus').value = data.status || 'active';
  document.getElementById('editStaffModal').style.display = 'flex';
}
function closeEditStaffModal() {
  document.getElementById('editStaffModal').style.display = 'none';
}

function openResetModal(id, name, email) {
  document.getElementById('modalUserId').value = id;
  document.getElementById('modalUserName').textContent = name;
  document.getElementById('modalUserEmail').textContent = email;
  document.getElementById('modalNewPassword').value = '';
  const modal = document.getElementById('resetPasswordModal');
  modal.style.display = 'flex';
}
function closeResetModal() {
  document.getElementById('resetPasswordModal').style.display = 'none';
}
function generateRandomPass() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  let pass = '';
  for (let i = 0; i < 10; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  document.getElementById('modalNewPassword').value = pass;
}

function generateNewStaffPass() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  let pass = 'Staff@';
  for (let i = 0; i < 4; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  const passInp = document.getElementById('newStaffPassword');
  passInp.value = pass;
  passInp.type = 'text';
}

function togglePassVisibility(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
  } else {
    inp.type = 'password';
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
  }
}

function copyStaffPortalUrl() {
  const codeEl = document.getElementById('staffPortalUrlCode');
  const url = codeEl ? codeEl.textContent.trim() : '';
  const btn = document.getElementById('copyStaffLinkBtn');
  copyToClipboard(url, () => {
    btn.innerHTML = '<i class="fa-solid fa-check" style="color:#00e074;"></i> Copied!';
    setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy Link'; }, 2000);
  });
}
</script>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/admin.js"></script>
</body>
</html>
