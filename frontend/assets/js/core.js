/* ============================================================
   MARVELOUS HOTEL — Global Core JS Utilities
   assets/js/core.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Auto dismiss flash notifications
  const flashes = document.querySelectorAll('.flash-message');
  flashes.forEach(flash => {
    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-10px)';
      flash.style.transition = 'all 0.4s ease';
      setTimeout(() => flash.remove(), 400);
    }, 4500);
  });

  // 2. Global Mobile Sidebar Drawer Toggle (Works for Admin, Staff, & User)
  const toggleBtns = document.querySelectorAll('[data-sidebar-toggle]');
  
  // Ensure a reusable mobile overlay exists
  let sidebarOverlay = document.getElementById('globalSidebarOverlay');
  if (!sidebarOverlay) {
    sidebarOverlay = document.createElement('div');
    sidebarOverlay.id = 'globalSidebarOverlay';
    sidebarOverlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);z-index:999;transition:opacity 0.25s ease;';
    document.body.appendChild(sidebarOverlay);
  }

  function setSidebarState(open) {
    const sidebar = document.querySelector('.admin-sidebar, .staff-sidebar, .user-sidebar, .sidebar');
    if (!sidebar) return;
    if (open) {
      sidebar.classList.add('open');
      sidebarOverlay.style.display = 'block';
      document.body.style.overflow = 'hidden'; // lock scroll on phones
    } else {
      sidebar.classList.remove('open');
      sidebarOverlay.style.display = 'none';
      document.body.style.overflow = '';
    }
  }

  toggleBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const sidebar = document.querySelector('.admin-sidebar, .staff-sidebar, .user-sidebar, .sidebar');
      const willOpen = !sidebar?.classList.contains('open');
      setSidebarState(willOpen);
    });
  });

  sidebarOverlay.addEventListener('click', () => setSidebarState(false));

  // Close sidebar on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setSidebarState(false);
  });
});

// Helper for modal controls
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('open');
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}

// Escape dynamic text before inserting into HTML strings
function escapeHtml(val) {
  return String(val ?? '').replace(/[&<>"']/g, function(ch) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
  });
}

// Clipboard helper with execCommand fallback for non-HTTPS local/LAN access
function copyToClipboard(text, onSuccess) {
  if (!text) return;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      if (typeof onSuccess === 'function') onSuccess();
    }).catch(() => copyViaFallbackTextarea(text, onSuccess));
  } else {
    copyViaFallbackTextarea(text, onSuccess);
  }
}

function copyViaFallbackTextarea(text, onSuccess) {
  const el = document.createElement('textarea');
  el.value = text;
  el.setAttribute('readonly', '');
  el.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0;';
  document.body.appendChild(el);
  el.select();
  try {
    if (document.execCommand('copy') && typeof onSuccess === 'function') {
      onSuccess();
    }
  } finally {
    document.body.removeChild(el);
  }
}

/**
 * Shared client-side Canvas image optimizer for avatar, ID, and receipt uploads.
 * Shrinks multi-megabyte phone camera images before submitting over mobile/tunnel connections.
 */
function optimizeImageInput(input, options = {}) {
  if (!input || !input.files || !input.files[0]) return;
  const file = input.files[0];
  if (!file.type || !file.type.startsWith('image/') || file.type === 'image/gif') return;

  const maxDim = options.maxDim || 1600;
  const minBytes = options.minBytes || (250 * 1024);
  const quality = options.quality || 0.85;
  const statusEl = options.statusElId ? document.getElementById(options.statusElId) : null;

  if (file.size <= minBytes) {
    if (statusEl) statusEl.style.display = 'none';
    if (typeof options.onDone === 'function') options.onDone(file);
    return;
  }

  if (statusEl) {
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Optimizing photo for fast upload...';
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = new Image();
    img.onload = function() {
      let width = img.width;
      let height = img.height;
      if (width > maxDim || height > maxDim) {
        if (width > height) {
          height = Math.round((height * maxDim) / width);
          width = maxDim;
        } else {
          width = Math.round((width * maxDim) / height);
          height = maxDim;
        }
      }
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, width, height);

      canvas.toBlob(function(blob) {
        if (blob && blob.size < file.size) {
          const newFile = new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
            type: 'image/jpeg',
            lastModified: Date.now()
          });
          const dt = new DataTransfer();
          dt.items.add(newFile);
          input.files = dt.files;

          if (statusEl) {
            const origLabel = file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + 'MB' : Math.round(file.size / 1024) + 'KB';
            const newLabel = blob.size >= 1048576 ? (blob.size / 1048576).toFixed(1) + 'MB' : Math.round(blob.size / 1024) + 'KB';
            statusEl.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#4ade80;"></i> Optimized from ${origLabel} to ${newLabel}.`;
          }
          if (typeof options.onDone === 'function') options.onDone(newFile);
        } else {
          if (statusEl) statusEl.style.display = 'none';
          if (typeof options.onDone === 'function') options.onDone(file);
        }
      }, 'image/jpeg', quality);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

