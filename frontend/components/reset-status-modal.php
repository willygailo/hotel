<?php
// ============================================================
// MARVELOUS HOTEL — Live Password Reset Status Popup Component
// includes/components/reset-status-modal.php
// ============================================================

if (!function_exists('renderPasswordResetStatusModal')) {
    function renderPasswordResetStatusModal(array $options = []): void
    {
        $watch = $_SESSION['password_reset_watch'] ?? null;
        $hasWatch = is_array($watch) && !empty($watch['token']);
        $autoStart = array_key_exists('auto_start', $options) ? (bool)$options['auto_start'] : $hasWatch;
        if (!$autoStart) {
            return;
        }

        $pollIntervalMs = max(2000, (int)($options['poll_interval_ms'] ?? 3000));
        $inlineStatusId = (string)($options['inline_status_id'] ?? '');
        $csrfToken = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
        $watchEmail = (string)($watch['email'] ?? '');
        ?>
<style>
  .pr-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(6, 9, 30, 0.82);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 10050;
    align-items: center;
    justify-content: center;
    padding: 18px;
    animation: prFadeIn 0.22s ease;
  }
  .pr-modal-backdrop.open {
    display: flex;
  }
  .pr-modal-card {
    width: 100%;
    max-width: 460px;
    background: linear-gradient(160deg, #141c4a 0%, #0d1336 100%);
    border: 1px solid rgba(201, 168, 76, 0.45);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.65), 0 0 32px rgba(201, 168, 76, 0.14);
    position: relative;
    color: #e2e8f0;
    animation: prSlideUp 0.26s cubic-bezier(0.2, 0.9, 0.25, 1);
  }
  .pr-modal-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #22c55e, #c9a84c);
    border-radius: 18px 18px 0 0;
  }
  .pr-modal-close {
    position: absolute;
    top: 14px;
    right: 16px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: #cbd5e1;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  }
  .pr-modal-close:hover {
    background: rgba(255,255,255,0.14);
    color: #fff;
  }
  .pr-modal-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    margin: 0 auto 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.65rem;
    background: rgba(34, 197, 94, 0.16);
    border: 1px solid rgba(34, 197, 94, 0.45);
    color: #4ade80;
    box-shadow: 0 0 24px rgba(34, 197, 94, 0.22);
  }
  .pr-cred-box {
    background: #070b22;
    border: 1px solid rgba(201, 168, 76, 0.45);
    border-radius: 12px;
    padding: 14px 16px;
    margin: 16px 0 12px;
  }
  .pr-cred-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #94a3b8;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .pr-cred-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .pr-cred-value {
    flex: 1;
    min-width: 140px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: #facc15;
    word-break: break-all;
    user-select: all;
    padding: 4px 0;
  }
  .pr-cred-btn {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.14);
    color: #e2e8f0;
    padding: 7px 11px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s ease;
    white-space: nowrap;
  }
  .pr-cred-btn:hover {
    background: rgba(201, 168, 76, 0.18);
    border-color: rgba(201, 168, 76, 0.5);
    color: #facc15;
  }
  .pr-cred-btn.copied {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22c55e;
    color: #4ade80;
  }
  .pr-tip-box {
    background: rgba(234, 179, 8, 0.09);
    border: 1px solid rgba(234, 179, 8, 0.28);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.78rem;
    color: #fde68a;
    line-height: 1.45;
    display: flex;
    gap: 9px;
    align-items: flex-start;
    margin-bottom: 18px;
  }
  .pr-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 0.76rem;
    color: #cbd5e1;
    margin-top: 8px;
  }
  @keyframes prFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  @keyframes prSlideUp {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }
  @media (max-width: 520px) {
    .pr-modal-card {
      padding: 22px 18px;
    }
    .pr-cred-value {
      font-size: 1rem;
    }
  }
</style>

<div id="passwordResetReadyModal" class="pr-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="prModalTitle">
  <div class="pr-modal-card">
    <button type="button" class="pr-modal-close" id="prModalCloseBtn" title="Close temporarily" aria-label="Close popup">&times;</button>

    <div style="text-align:center;">
      <div class="pr-modal-icon" id="prModalIconWrap">
        <i class="fa-solid fa-circle-check" id="prModalIcon"></i>
      </div>
      <h3 id="prModalTitle" style="font-family:var(--font-serif);font-size:1.4rem;color:#fff;margin:0 0 6px;">
        Password Updated by Admin!
      </h3>
      <p id="prModalSubtitle" style="font-size:0.86rem;color:#cbd5e1;margin:0;line-height:1.5;">
        The Hotel Admin has updated the password for your account.
      </p>
      <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:6px;">
        <span class="pr-meta-pill"><i class="fa-solid fa-envelope" style="color:var(--gold);"></i> <span id="prModalEmail"><?= h($watchEmail) ?></span></span>
        <span class="pr-meta-pill" id="prModalTimeWrap" style="display:none;"><i class="fa-solid fa-clock" style="color:#4ade80;"></i> <span id="prModalTime"></span></span>
      </div>
    </div>

    <!-- Section A: Direct Assigned Temporary Password -->
    <div id="prPasswordSection" style="display:none;">
      <div class="pr-cred-box">
        <div class="pr-cred-label">
          <span><i class="fa-solid fa-key" style="color:var(--gold);margin-right:4px;"></i> New Temporary Password</span>
          <span style="color:#4ade80;font-weight:600;">One-Time View</span>
        </div>
        <div class="pr-cred-row">
          <div class="pr-cred-value" id="prPasswordDisplay">••••••••••</div>
          <button type="button" class="pr-cred-btn" id="prTogglePassBtn">
            <i class="fa-solid fa-eye"></i> <span>Show</span>
          </button>
          <button type="button" class="pr-cred-btn" id="prCopyPassBtn">
            <i class="fa-regular fa-copy"></i> <span>Copy</span>
          </button>
        </div>
      </div>

      <div class="pr-tip-box">
        <i class="fa-solid fa-shield-halved" style="margin-top:2px;flex-shrink:0;"></i>
        <span>Copy your temporary password before leaving. For your security, this popup will not reappear once dismissed — change your password in <strong>Profile Settings</strong> after logging in.</span>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;">
        <button type="button" class="btn btn-gold btn-block" id="prProceedLoginBtn">
          <i class="fa-solid fa-right-to-bracket"></i> Proceed to Login &rarr;
        </button>
        <button type="button" class="btn btn-ghost btn-block" id="prDismissConsumeBtn" style="font-size:0.84rem;">
          <i class="fa-solid fa-check"></i> I've Saved It, Dismiss Popup
        </button>
      </div>
    </div>

    <!-- Section B: Admin-Generated One-Time Reset Link -->
    <div id="prLinkSection" style="display:none;margin-top:18px;">
      <div class="pr-tip-box" style="background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:#bbf7d0;">
        <i class="fa-solid fa-link" style="margin-top:2px;flex-shrink:0;"></i>
        <span>The Hotel Admin generated a secure one-time password reset link for your account. Click the button below to create your new password.</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <button type="button" class="btn btn-gold btn-block" id="prOpenResetLinkBtn">
          <i class="fa-solid fa-key"></i> Set New Password Now &rarr;
        </button>
        <button type="button" class="btn btn-ghost btn-block" id="prDismissLinkBtn" style="font-size:0.84rem;">
          Dismiss
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const POLL_URL = 'check-reset-status.php';
  const POLL_INTERVAL = <?= (int)$pollIntervalMs ?>;
  const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const INLINE_CARD_ID = <?= json_encode($inlineStatusId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const ORIGINAL_TITLE = document.title;

  let pollTimer = null;
  let inFlight = false;
  let latestCompletedPayload = null;
  let passwordVisible = false;
  let hasNotified = false;

  const modal = document.getElementById('passwordResetReadyModal');
  const closeBtn = document.getElementById('prModalCloseBtn');
  const titleEl = document.getElementById('prModalTitle');
  const subEl = document.getElementById('prModalSubtitle');
  const iconWrap = document.getElementById('prModalIconWrap');
  const iconEl = document.getElementById('prModalIcon');
  const emailEl = document.getElementById('prModalEmail');
  const timeWrap = document.getElementById('prModalTimeWrap');
  const timeEl = document.getElementById('prModalTime');

  const passSection = document.getElementById('prPasswordSection');
  const passDisplay = document.getElementById('prPasswordDisplay');
  const togglePassBtn = document.getElementById('prTogglePassBtn');
  const copyPassBtn = document.getElementById('prCopyPassBtn');
  const proceedLoginBtn = document.getElementById('prProceedLoginBtn');
  const dismissConsumeBtn = document.getElementById('prDismissConsumeBtn');

  const linkSection = document.getElementById('prLinkSection');
  const openResetLinkBtn = document.getElementById('prOpenResetLinkBtn');
  const dismissLinkBtn = document.getElementById('prDismissLinkBtn');

  function playSubtleChime() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(660, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.14);
      gain.gain.setValueAtTime(0.06, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    } catch (e) {}
  }

  function renderPasswordMask() {
    if (!latestCompletedPayload || !latestCompletedPayload.new_password) return;
    if (passwordVisible) {
      passDisplay.textContent = latestCompletedPayload.new_password;
      togglePassBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> <span>Hide</span>';
    } else {
      const len = Math.max(8, Math.min(14, latestCompletedPayload.new_password.length));
      passDisplay.textContent = '•'.repeat(len);
      togglePassBtn.innerHTML = '<i class="fa-solid fa-eye"></i> <span>Show</span>';
    }
  }

  const safeHtml = (v) => (typeof escapeHtml === 'function')
    ? escapeHtml(v)
    : String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

  function updateInlineCard(state, payload) {
    if (!INLINE_CARD_ID) return;
    const card = document.getElementById(INLINE_CARD_ID);
    if (!card) return;

    if (state === 'completed') {
      const isLink = payload && payload.dispatch_type === 'link';
      card.className = 'reset-watch-card is-ready';
      card.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:12px;">
          <div class="reset-watch-icon is-ready"><i class="fa-solid fa-circle-check"></i></div>
          <div style="flex:1;">
            <div style="font-weight:700;color:#4ade80;font-size:0.95rem;">
              ${isLink ? 'Password Reset Link Ready!' : 'Your Password Has Been Updated!'}
            </div>
            <div style="font-size:0.82rem;color:#cbd5e1;margin-top:3px;line-height:1.45;">
              The Hotel Admin has processed your request for <strong>${safeHtml(payload.email || '')}</strong>.
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
              <button type="button" class="btn btn-gold btn-sm" onclick="window.MarvelousResetWatcher.openModal()">
                <i class="fa-solid fa-key"></i> ${isLink ? 'Open Reset Popup' : 'View New Password'}
              </button>
            </div>
          </div>
        </div>
      `;
    } else if (state === 'expired') {
      card.className = 'reset-watch-card is-expired';
      card.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:12px;">
          <div class="reset-watch-icon is-expired"><i class="fa-solid fa-clock"></i></div>
          <div style="flex:1;">
            <div style="font-weight:700;color:#fbbf24;font-size:0.92rem;">Reset Notification Expired</div>
            <div style="font-size:0.82rem;color:#cbd5e1;margin-top:3px;line-height:1.45;">
              For your security, the one-time password view has expired. Please submit a new request below or ask the frontdesk for assistance.
            </div>
          </div>
        </div>
      `;
    } else if (state === 'acknowledged' || state === 'none') {
      card.style.display = 'none';
    }
  }

  function openModalWithPayload(payload) {
    latestCompletedPayload = payload;
    passwordVisible = false;

    if (emailEl && payload.email) {
      emailEl.textContent = payload.email;
    }
    if (timeWrap && timeEl) {
      if (payload.resolved_at) {
        timeEl.textContent = payload.resolved_at;
        timeWrap.style.display = 'inline-flex';
      } else {
        timeWrap.style.display = 'none';
      }
    }

    if (payload.dispatch_type === 'link') {
      titleEl.textContent = 'Password Reset Link Ready!';
      subEl.textContent = 'The Hotel Admin has approved your request and prepared a one-time reset link.';
      iconWrap.style.background = 'rgba(201,168,76,0.16)';
      iconWrap.style.borderColor = 'rgba(201,168,76,0.45)';
      iconWrap.style.color = '#facc15';
      iconEl.className = 'fa-solid fa-link';
      passSection.style.display = 'none';
      linkSection.style.display = 'block';
    } else {
      titleEl.textContent = 'Password Updated by Admin!';
      subEl.textContent = 'Your password has been updated. Use the new temporary password below to log in.';
      iconWrap.style.background = 'rgba(34,197,94,0.16)';
      iconWrap.style.borderColor = 'rgba(34,197,94,0.45)';
      iconWrap.style.color = '#4ade80';
      iconEl.className = 'fa-solid fa-circle-check';
      linkSection.style.display = 'none';
      passSection.style.display = 'block';
      renderPasswordMask();
    }

    modal.classList.add('open');
    document.title = '(Updated!) ' + ORIGINAL_TITLE;

    if (!hasNotified) {
      hasNotified = true;
      playSubtleChime();
    }
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  async function postAction(action) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('csrf_token', CSRF_TOKEN);
    try {
      await fetch(POLL_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': CSRF_TOKEN,
          'Accept': 'application/json'
        },
        body: body.toString()
      });
    } catch (e) {}
  }

  async function checkStatus(isManual) {
    if (inFlight) return;
    inFlight = true;
    const manualBtn = document.getElementById('prManualCheckBtn');
    if (isManual && manualBtn) {
      manualBtn.disabled = true;
      manualBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    }

    try {
      const res = await fetch(POLL_URL, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return;
      const data = await res.json();

      if (data.status === 'completed') {
        stopPolling();
        updateInlineCard('completed', data);
        openModalWithPayload(data);
      } else if (data.status === 'expired') {
        stopPolling();
        updateInlineCard('expired', data);
      } else if (data.status === 'acknowledged' || data.status === 'none') {
        stopPolling();
        updateInlineCard(data.status, data);
      }
    } catch (e) {
      // Transient network error — keep polling quietly
    } finally {
      inFlight = false;
      if (isManual && manualBtn) {
        manualBtn.disabled = false;
        manualBtn.innerHTML = '<i class="fa-solid fa-rotate"></i> Check Now';
      }
    }
  }

  function startPolling() {
    stopPolling();
    checkStatus(false);
    pollTimer = setInterval(() => {
      if (!document.hidden) {
        checkStatus(false);
      }
    }, POLL_INTERVAL);
  }

  // Event wiring
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      modal.classList.remove('open');
    });
  }

  if (togglePassBtn) {
    togglePassBtn.addEventListener('click', () => {
      passwordVisible = !passwordVisible;
      renderPasswordMask();
    });
  }

  if (copyPassBtn) {
    copyPassBtn.addEventListener('click', () => {
      if (!latestCompletedPayload || !latestCompletedPayload.new_password) return;
      const onCopied = () => {
        copyPassBtn.classList.add('copied');
        copyPassBtn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copied!</span>';
        setTimeout(() => {
          copyPassBtn.classList.remove('copied');
          copyPassBtn.innerHTML = '<i class="fa-regular fa-copy"></i> <span>Copy</span>';
        }, 2200);
      };
      if (typeof copyToClipboard === 'function') {
        copyToClipboard(latestCompletedPayload.new_password, onCopied);
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(latestCompletedPayload.new_password).then(onCopied).catch(() => {});
      }
    });
  }

  async function acknowledgeAndGo(url) {
    stopPolling();
    await postAction('acknowledge');
    document.title = ORIGINAL_TITLE;
    window.location.href = url;
  }

  async function acknowledgeAndDismiss() {
    stopPolling();
    await postAction('acknowledge');
    modal.classList.remove('open');
    document.title = ORIGINAL_TITLE;
    latestCompletedPayload = null;
    updateInlineCard('acknowledged', null);
  }

  if (proceedLoginBtn) {
    proceedLoginBtn.addEventListener('click', () => {
      const target = (latestCompletedPayload && latestCompletedPayload.login_url) ? latestCompletedPayload.login_url : 'login.php';
      acknowledgeAndGo(target);
    });
  }

  if (dismissConsumeBtn) {
    dismissConsumeBtn.addEventListener('click', acknowledgeAndDismiss);
  }

  if (openResetLinkBtn) {
    openResetLinkBtn.addEventListener('click', () => {
      const target = (latestCompletedPayload && latestCompletedPayload.reset_url) ? latestCompletedPayload.reset_url : 'forgot-password.php';
      acknowledgeAndGo(target);
    });
  }

  if (dismissLinkBtn) {
    dismissLinkBtn.addEventListener('click', acknowledgeAndDismiss);
  }

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !latestCompletedPayload && pollTimer) {
      checkStatus(false);
    }
  });

  window.MarvelousResetWatcher = {
    checkNow: () => checkStatus(true),
    openModal: () => {
      if (latestCompletedPayload) {
        openModalWithPayload(latestCompletedPayload);
      }
    },
    cancelWatch: async () => {
      stopPolling();
      await postAction('cancel');
      document.title = ORIGINAL_TITLE;
      updateInlineCard('none', null);
      window.location.href = 'forgot-password.php';
    }
  };

  startPolling();
})();
</script>
        <?php
    }
}
